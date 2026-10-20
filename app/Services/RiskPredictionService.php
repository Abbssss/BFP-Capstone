<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\ModelVersion;
use App\Support\OfficeScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class RiskPredictionService
{
    public function modelPath(): string
    {
        return storage_path('app/ml/trained_risk_model.joblib');
    }

    public function isModelReady(): bool
    {
        return is_file($this->modelPath());
    }

    public function scoreEquipment(Equipment $equipment): array
    {
        if (! $this->isModelReady()) {
            $training = $this->retrain();
            if ($training['status'] !== 'trained') {
                throw new \RuntimeException('The risk model could not be trained: '.($training['details'] ?? 'unknown training error'));
            }
        }

        $payload = $this->featuresFor($equipment);
        $inputPath = storage_path('app/ml/prediction-'.bin2hex(random_bytes(8)).'.json');
        $this->writeJson($inputPath, ['records' => [$payload]]);

        try {
            $result = $this->runPython('predict', $inputPath);
            $prediction = $result['predictions'][0] ?? null;
            if (! is_array($prediction) || ! isset($prediction['risk_label'], $prediction['confidence'])) {
                throw new \RuntimeException('The model returned an invalid prediction response.');
            }

            return [
                'label' => $prediction['risk_label'],
                'confidence' => (float) $prediction['confidence'],
            ];
        } finally {
            if (is_file($inputPath)) {
                unlink($inputPath);
            }
        }
    }

    public function retrain(): array
    {
        $directory = storage_path('app/ml');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return ['status' => 'failed', 'details' => 'Unable to create the model storage directory.'];
        }

        $datasetPath = $directory.DIRECTORY_SEPARATOR.'training_data.csv';
        try {
            $equipment = OfficeScope::equipment(Equipment::query())->get();
            if ($equipment->isNotEmpty()) {
                $this->writeEquipmentDataset($datasetPath, $equipment);
            }

            $trainingSource = $equipment->isNotEmpty()
                ? 'equipment operational records with rule-derived risk labels'
                : 'built-in baseline dataset';
            $result = $this->runPython('train', $datasetPath, $trainingSource);
            if (($result['status'] ?? null) !== 'trained' || ! $this->isModelReady()) {
                throw new \RuntimeException('The training process did not create a usable model file.');
            }

            $metrics = $result['metrics'] ?? [];
            $evaluated = (bool) ($metrics['evaluated'] ?? false);
            $metadata = [
                'evaluated' => $evaluated,
                'evaluation_rows' => (int) ($metrics['evaluation_rows'] ?? 0),
                'training_rows' => (int) ($result['training_rows'] ?? 0),
                'training_source' => $result['training_source'] ?? 'unknown',
                'feature_importance' => $result['feature_importance'] ?? [],
            ];
            $versionName = 'v'.now()->format('YmdHis').Str::upper(Str::random(4));
            $version = ModelVersion::create([
                'version_name' => $versionName,
                'accuracy' => $evaluated ? (float) ($metrics['accuracy'] ?? 0) : 0,
                'precision' => $evaluated ? (float) ($metrics['precision'] ?? 0) : 0,
                'recall' => $evaluated ? (float) ($metrics['recall'] ?? 0) : 0,
                'f1_score' => $evaluated ? (float) ($metrics['f1_score'] ?? 0) : 0,
                'confusion_matrix' => json_encode($metrics['confusion_matrix'] ?? [], JSON_THROW_ON_ERROR),
                'training_date' => now(),
                'notes' => json_encode($metadata, JSON_THROW_ON_ERROR),
            ]);

            return [
                'status' => 'trained',
                'model_version' => $version->version_name,
                'metrics' => $metrics,
                'training_rows' => $metadata['training_rows'],
                'training_source' => $metadata['training_source'],
            ];
        } catch (Throwable $exception) {
            Log::error('Risk model training failed.', ['exception' => $exception]);

            return ['status' => 'failed', 'details' => $exception->getMessage()];
        }
    }

    public function latestModelMetadata(?ModelVersion $version): array
    {
        if (! $version || ! $version->notes) {
            return [];
        }

        $metadata = json_decode($version->notes, true);

        return is_array($metadata) ? $metadata : [];
    }

    protected function featuresFor(Equipment $equipment): array
    {
        return [
            'equipment_id' => $equipment->id,
            'service_age' => (float) ($equipment->service_age_years ?? 0),
            'engine_operating_hours' => (float) ($equipment->engine_operating_hours ?? 0),
            'runtime_cycles' => (float) ($equipment->runtime_cycles ?? 0),
            'mechanical_failures' => (float) ($equipment->mechanical_failure_count ?? 0),
            'days_since_last_inspection' => $this->daysSince($equipment->last_inspection_date),
            'days_since_last_maintenance' => $this->daysSince($equipment->last_maintenance_date),
            'defects_count' => (int) ($equipment->inspection_defects_count ?? 0),
            'inspection_outcome' => $this->inspectionOutcomeScore($equipment->last_inspection_outcome ?? 'Passed'),
            'maintenance_frequency' => (float) ($equipment->maintenance_interval_days ?? 365),
        ];
    }

    protected function writeEquipmentDataset(string $path, $equipment): void
    {
        $temporaryPath = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        $csv = fopen($temporaryPath, 'wb');
        if ($csv === false) {
            throw new \RuntimeException('Unable to create the model training dataset.');
        }

        try {
            fputcsv($csv, [
                'service_age', 'engine_operating_hours', 'runtime_cycles', 'mechanical_failures',
                'days_since_last_inspection', 'days_since_last_maintenance', 'defects_count',
                'maintenance_frequency', 'inspection_outcome', 'risk_label',
            ]);

            foreach ($equipment as $item) {
                $features = $this->featuresFor($item);
                $features['risk_label'] = $this->riskClass($features);
                unset($features['equipment_id']);
                fputcsv($csv, array_values($features));
            }
        } finally {
            fclose($csv);
        }

        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Unable to finalize the model training dataset.');
        }
    }

    protected function runPython(string $mode, string $inputPath, ?string $trainingSource = null): array
    {
        $python = $this->resolvePythonExecutable();
        $arguments = [
            $python,
            base_path('python/risk_model.py'),
            $mode,
            '--input',
            $inputPath,
            '--model',
            $this->modelPath(),
        ];
        if ($mode === 'train') {
            $arguments[] = '--source';
            $arguments[] = $trainingSource ?? 'stored training dataset';
        }

        $process = new Process($arguments, base_path());
        $process->setEnv($this->windowsProcessEnvironment());
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            $details = trim($process->getErrorOutput()."\n".$process->getOutput());
            throw new \RuntimeException($details !== '' ? $details : 'Python model process exited unsuccessfully.');
        }

        try {
            $result = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('The Python model returned invalid JSON: '.$process->getOutput(), previous: $exception);
        }

        if (! is_array($result)) {
            throw new \RuntimeException('The Python model returned an invalid response.');
        }

        return $result;
    }

    protected function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write the equipment prediction input.');
        }
    }

    protected function resolvePythonExecutable(): string
    {
        $configured = getenv('PYTHON_BINARY');
        $candidates = array_filter([
            $configured ?: null,
            'python',
            'python3',
            'py',
        ]);

        foreach ($candidates as $candidate) {
            $arguments = $candidate === 'py' ? ['py', '-3', '--version'] : [$candidate, '--version'];
            try {
                $process = new Process($arguments, base_path());
                $process->setEnv($this->windowsProcessEnvironment());
                $process->setTimeout(5);
                $process->run();
                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (Throwable) {
                continue;
            }
        }

        throw new \RuntimeException('Python was not found. Install Python and set PYTHON_BINARY to its executable path.');
    }

    protected function windowsProcessEnvironment(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $systemRoot = getenv('SystemRoot') ?: getenv('WINDIR') ?: 'C:\\Windows';

        return [
            'SystemRoot' => $systemRoot,
            'SYSTEMROOT' => $systemRoot,
            'WINDIR' => $systemRoot,
            'TEMP' => getenv('TEMP') ?: sys_get_temp_dir(),
            'TMP' => getenv('TMP') ?: sys_get_temp_dir(),
            'PATH' => getenv('PATH') ?: '',
        ];
    }

    protected function daysSince($date): int
    {
        return $date ? max(0, (int) Carbon::parse($date)->diffInDays(now(), false)) : 0;
    }

    protected function inspectionOutcomeScore(string $status): int
    {
        return match ($status) {
            'Passed' => 1,
            'Passed with Findings' => 2,
            'Failed' => 3,
            'Requires Further Assessment' => 4,
            default => 1,
        };
    }

    protected function riskClass(array $features): int
    {
        $score = 0;
        $score += (float) ($features['service_age'] ?? 0) * 2;
        $score += (float) ($features['engine_operating_hours'] ?? 0) / 100;
        $score += (float) ($features['runtime_cycles'] ?? 0) / 20;
        $score += (float) ($features['mechanical_failures'] ?? 0) * 3;
        $score += (float) ($features['days_since_last_inspection'] ?? 0) / 30;
        $score += (float) ($features['days_since_last_maintenance'] ?? 0) / 45;
        $score += (float) ($features['defects_count'] ?? 0) * 2;
        $score += (float) ($features['inspection_outcome'] ?? 1) * 2;
        $score += (float) ($features['maintenance_frequency'] ?? 365) / 180;

        return $score >= 20 ? 2 : ($score >= 10 ? 1 : 0);
    }
}
