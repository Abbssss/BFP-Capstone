#!/usr/bin/env python3
import argparse
import json
import os

import joblib
import pandas as pd
from sklearn.metrics import accuracy_score, confusion_matrix, f1_score, precision_score, recall_score
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.tree import DecisionTreeClassifier

FEATURE_COLUMNS = [
    'service_age',
    'engine_operating_hours',
    'runtime_cycles',
    'mechanical_failures',
    'days_since_last_inspection',
    'days_since_last_maintenance',
    'defects_count',
    'maintenance_frequency',
    'inspection_outcome',
]

RISK_MAP = {0: 'Low Risk', 1: 'Medium Risk', 2: 'High Risk'}
LABEL_MAP = {value: key for key, value in RISK_MAP.items()}


def fallback_dataset():
    return pd.DataFrame([
        {'service_age': 1, 'engine_operating_hours': 200, 'runtime_cycles': 10, 'mechanical_failures': 0, 'days_since_last_inspection': 20, 'days_since_last_maintenance': 35, 'defects_count': 0, 'maintenance_frequency': 365, 'inspection_outcome': 1, 'risk_label': 0},
        {'service_age': 4, 'engine_operating_hours': 900, 'runtime_cycles': 50, 'mechanical_failures': 2, 'days_since_last_inspection': 70, 'days_since_last_maintenance': 90, 'defects_count': 2, 'maintenance_frequency': 180, 'inspection_outcome': 2, 'risk_label': 1},
        {'service_age': 7, 'engine_operating_hours': 1600, 'runtime_cycles': 120, 'mechanical_failures': 4, 'days_since_last_inspection': 140, 'days_since_last_maintenance': 150, 'defects_count': 5, 'maintenance_frequency': 90, 'inspection_outcome': 3, 'risk_label': 2},
        {'service_age': 8, 'engine_operating_hours': 2200, 'runtime_cycles': 100, 'mechanical_failures': 5, 'days_since_last_inspection': 150, 'days_since_last_maintenance': 180, 'defects_count': 4, 'maintenance_frequency': 120, 'inspection_outcome': 3, 'risk_label': 2},
        {'service_age': 2, 'engine_operating_hours': 300, 'runtime_cycles': 15, 'mechanical_failures': 1, 'days_since_last_inspection': 40, 'days_since_last_maintenance': 60, 'defects_count': 1, 'maintenance_frequency': 310, 'inspection_outcome': 1, 'risk_label': 0},
        {'service_age': 3, 'engine_operating_hours': 600, 'runtime_cycles': 35, 'mechanical_failures': 1, 'days_since_last_inspection': 80, 'days_since_last_maintenance': 70, 'defects_count': 2, 'maintenance_frequency': 240, 'inspection_outcome': 2, 'risk_label': 1},
        {'service_age': 5, 'engine_operating_hours': 1200, 'runtime_cycles': 80, 'mechanical_failures': 3, 'days_since_last_inspection': 90, 'days_since_last_maintenance': 100, 'defects_count': 3, 'maintenance_frequency': 160, 'inspection_outcome': 2, 'risk_label': 1},
    ])


def read_training_data(path: str):
    if not os.path.exists(path) or os.path.getsize(path) == 0:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        data = fallback_dataset()
        data.to_csv(path, index=False)
        return data, 'built-in baseline dataset'

    data = pd.read_csv(path)
    if data.empty:
        data = fallback_dataset()
        return data, 'built-in baseline dataset'
    if 'risk_label' not in data.columns:
        raise ValueError('The training dataset must include a risk_label column.')

    missing_features = [column for column in FEATURE_COLUMNS if column not in data.columns]
    if missing_features:
        raise ValueError('Training data is missing required columns: ' + ', '.join(missing_features))

    data = data.dropna(subset=FEATURE_COLUMNS + ['risk_label']).copy()
    if data.empty:
        raise ValueError('Training data has no complete feature and risk-label rows.')

    data['risk_label'] = data['risk_label'].map(
        lambda label: LABEL_MAP.get(label, label) if isinstance(label, str) else label
    )
    data['risk_label'] = pd.to_numeric(data['risk_label'], errors='coerce')
    data = data.dropna(subset=['risk_label'])
    if data.empty or not data['risk_label'].isin(RISK_MAP).all():
        raise ValueError('Risk labels must be 0, 1, 2 or Low Risk, Medium Risk, High Risk.')

    return data, 'equipment operational records'


def build_model():
    return Pipeline([
        ('classifier', DecisionTreeClassifier(random_state=42, max_depth=4, class_weight='balanced')),
    ])


def train_model(path: str, model_path: str, training_source: str):
    data, _ = read_training_data(path)
    X = data[FEATURE_COLUMNS].apply(pd.to_numeric, errors='raise')
    y = data['risk_label'].astype(int)
    model = build_model()

    class_counts = y.value_counts()
    can_evaluate = len(data) >= 6 and len(class_counts) >= 2 and int(class_counts.min()) >= 2
    if can_evaluate:
        test_size = max(len(class_counts), int(round(len(data) * 0.25)))
        if len(data) - test_size < len(class_counts):
            test_size = len(data) - len(class_counts)
        if test_size >= len(class_counts):
            X_train, X_test, y_train, y_test = train_test_split(
                X,
                y,
                test_size=test_size,
                random_state=42,
                stratify=y,
            )
            evaluation_model = build_model()
            evaluation_model.fit(X_train, y_train)
            predictions = evaluation_model.predict(X_test)
            labels = sorted(int(label) for label in y.unique())
            metrics = {
                'accuracy': round(float(accuracy_score(y_test, predictions)), 4),
                'precision': round(float(precision_score(y_test, predictions, average='weighted', zero_division=0)), 4),
                'recall': round(float(recall_score(y_test, predictions, average='weighted', zero_division=0)), 4),
                'f1_score': round(float(f1_score(y_test, predictions, average='weighted', zero_division=0)), 4),
                'confusion_matrix': confusion_matrix(y_test, predictions, labels=labels).tolist(),
                'evaluated': True,
                'evaluation_rows': int(len(y_test)),
            }
        else:
            can_evaluate = False

    if not can_evaluate:
        metrics = {
            'accuracy': None,
            'precision': None,
            'recall': None,
            'f1_score': None,
            'confusion_matrix': [],
            'evaluated': False,
            'evaluation_rows': 0,
        }

    model.fit(X, y)
    os.makedirs(os.path.dirname(model_path), exist_ok=True)
    joblib.dump(model, model_path)
    feature_importance = model.named_steps['classifier'].feature_importances_
    print(json.dumps({
        'status': 'trained',
        'metrics': metrics,
        'feature_importance': {
            feature: round(float(importance), 4)
            for feature, importance in zip(FEATURE_COLUMNS, feature_importance)
        },
        'training_rows': int(len(data)),
        'training_source': training_source,
        'model_path': model_path,
    }))


def predict_model(path: str, model_path: str):
    with open(path, 'r', encoding='utf-8') as input_file:
        payload = json.load(input_file)
    records = payload.get('records', [])
    if not records:
        raise ValueError('No equipment records were provided.')
    if not os.path.isfile(model_path):
        raise FileNotFoundError('The trained model is missing. Retrain the model first.')

    model = joblib.load(model_path)
    data = pd.DataFrame.from_records(records)
    for feature in FEATURE_COLUMNS:
        if feature not in data.columns:
            data[feature] = 0

    predictions = model.predict(data[FEATURE_COLUMNS])
    probabilities = model.predict_proba(data[FEATURE_COLUMNS])
    output = []
    for index, (label, scores) in enumerate(zip(predictions, probabilities)):
        class_index = list(model.classes_).index(label)
        output.append({
            'index': index,
            'risk_label': RISK_MAP[int(label)],
            'confidence': round(float(scores[class_index]), 4),
        })

    print(json.dumps({'predictions': output}))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Fire equipment risk model')
    parser.add_argument('mode', choices=['train', 'predict'])
    parser.add_argument('--input', required=True)
    parser.add_argument('--model', required=True)
    parser.add_argument('--source', default='stored training dataset')
    args = parser.parse_args()

    if args.mode == 'train':
        train_model(args.input, args.model, args.source)
    else:
        predict_model(args.input, args.model)
