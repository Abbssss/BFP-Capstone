<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\ModelVersion;
use App\Models\RiskPrediction;
use App\Services\RiskPredictionService;
use App\Support\OfficeScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class RiskController extends Controller
{
    public function index(RiskPredictionService $service): View
    {
        $equipment = OfficeScope::equipment(Equipment::query())->latest()->get();
        $versions = ModelVersion::latest('training_date')->get();
        $predictions = OfficeScope::records(RiskPrediction::query())->with('equipment', 'modelVersion')->latest('prediction_timestamp')->limit(20)->get();
        $predictionCount = OfficeScope::records(RiskPrediction::query())->count();
        $latestVersion = $versions->first();
        $modelMetadata = $service->latestModelMetadata($latestVersion);
        $featureImportance = collect($modelMetadata['feature_importance'] ?? [])
            ->sortDesc()
            ->take(8);
        $modelReady = $service->isModelReady();

        $highRisk = $predictions->filter(fn ($prediction) => $prediction->prediction_label === 'High Risk')->count();
        $mediumRisk = $predictions->filter(fn ($prediction) => $prediction->prediction_label === 'Medium Risk')->count();
        $lowRisk = $predictions->filter(fn ($prediction) => $prediction->prediction_label === 'Low Risk')->count();

        return view('risk.index', compact('equipment', 'versions', 'predictions', 'predictionCount', 'latestVersion', 'modelMetadata', 'featureImportance', 'modelReady', 'highRisk', 'mediumRisk', 'lowRisk'));
    }

    public function run(Request $request, RiskPredictionService $service): RedirectResponse
    {
        $validated = $request->validate([
            'equipment_id' => ['required', OfficeScope::equipmentExistsRule()],
        ]);
        $equipment = OfficeScope::equipment(Equipment::query())->findOrFail($validated['equipment_id']);

        try {
            $result = $service->scoreEquipment($equipment);
            $version = ModelVersion::latest('training_date')->first();

            RiskPrediction::create([
                'equipment_id' => $equipment->id,
                'model_version_id' => $version?->id,
                'prediction_label' => $result['label'],
                'confidence' => $result['confidence'],
                'prediction_timestamp' => now(),
                'details_json' => json_encode($result, JSON_THROW_ON_ERROR),
            ]);

            return back()->with('success', 'Model assessment for '.$equipment->equipment_name.': '.$result['label'].' ('.number_format($result['confidence'] * 100, 1).'%).');
        } catch (Throwable $exception) {
            Log::error('Equipment risk assessment failed.', [
                'equipment_id' => $equipment->id,
                'exception' => $exception,
            ]);

            return back()->with('error', 'Risk assessment failed: '.$exception->getMessage());
        }
    }

    public function retrain(RiskPredictionService $service): RedirectResponse
    {
        $result = $service->retrain();

        $message = $result['status'] === 'trained'
            ? sprintf(
                'Model %s trained on %d records using %s.%s',
                $result['model_version'],
                $result['training_rows'],
                $result['training_source'],
                ($result['metrics']['evaluated'] ?? false) ? ' Held-out evaluation metrics are available.' : ' Not enough labeled records were available for independent evaluation.'
            )
            : 'Model retraining failed: '.$result['details'];

        return back()->with(
            $result['status'] === 'trained' ? 'success' : 'error',
            $message
        );
    }
}
