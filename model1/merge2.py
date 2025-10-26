import pandas as pd
import numpy as np

# Load all your sample files
sample0 = pd.read_csv('normal_breath_sample0.csv')  # Your first flow asynchrony sample
sample1 = pd.read_csv('normal_breath_sample_1.csv')  # Your second normal sample
sample2 = pd.read_csv('normal_breath_sample2.csv')  # Your third normal sample


# Standardize column names across all samples
def standardize_columns(df, sample_id):
    """Ensure all dataframes have consistent column names and structure"""
    df_standardized = df.copy()

    # Rename columns to be consistent
    column_mapping = {
        'volume': 'vol',  # If some use 'volume' and others use 'vol'
        'pressure': 'paw',  # If some use 'pressure' and others use 'paw'
    }

    df_standardized = df_standardized.rename(columns=column_mapping)

    # Ensure sample_id is consistent
    df_standardized['sample_id'] = sample_id

    return df_standardized


# Standardize each sample
sample0_std = standardize_columns(sample0, 0)
sample1_std = standardize_columns(sample1, 1)
sample2_std = standardize_columns(sample2, 2)

print("\n=== AFTER STANDARDIZATION ===")
print(f"Sample 0 columns: {sample0_std.columns.tolist()}")
print(f"Sample 1 columns: {sample1_std.columns.tolist()}")
print(f"Sample 2 columns: {sample2_std.columns.tolist()}")

# Get all unique columns across all samples
all_columns = set()
for sample in [sample0_std, sample1_std, sample2_std]:
    all_columns.update(sample.columns.tolist())

print(f"\nAll unique columns: {sorted(all_columns)}")

# Combine all samples
combined_df = pd.concat([sample0_std, sample1_std, sample2_std], ignore_index=True)

print(f"\n=== COMBINED DATASET ===")
print(f"Combined shape: {combined_df.shape}")
print(f"Total samples: {combined_df['sample_id'].nunique()}")
print(f"Total data points: {len(combined_df)}")

# Display sample distribution
print("\n=== SAMPLE DISTRIBUTION ===")
sample_summary = combined_df.groupby('sample_id').agg({
    'anomaly': 'first',
    'anomaly_type': 'first',
    'data_modality': 'first',
    'time': ['count', 'min', 'max']
}).round(3)

print(sample_summary)

# Display anomaly distribution
print("\n=== ANOMALY DISTRIBUTION ===")
anomaly_summary = combined_df.groupby(['sample_id', 'anomaly', 'anomaly_type']).size().reset_index(name='count')
print(anomaly_summary)

# Check available signals per sample
print("\n=== AVAILABLE SIGNALS PER SAMPLE ===")
for sample_id in combined_df['sample_id'].unique():
    sample_data = combined_df[combined_df['sample_id'] == sample_id]
    available_signals = []
    for signal in ['flow', 'vol', 'paw', 'volume', 'pressure']:
        if signal in sample_data.columns and not sample_data[signal].isna().all():
            available_signals.append(signal)
    print(f"Sample {sample_id}: {available_signals}")

# Final column cleanup - ensure consistent naming
final_columns = ['sample_id', 'time', 'anomaly', 'anomaly_type']
signal_columns = []

# Add available signal columns
for signal in ['flow', 'vol', 'paw']:
    if signal in combined_df.columns:
        signal_columns.append(signal)

# Add metadata columns
metadata_columns = [col for col in ['data_modality', 'description'] if col in combined_df.columns]

# Reorder columns
combined_df = combined_df[final_columns + signal_columns + metadata_columns]

print(f"\nFinal columns: {combined_df.columns.tolist()}")

# Save combined dataset
combined_df.to_csv('Normal_waves.csv', index=False)

# Create a summary report
print("\n=== DATASET SUMMARY REPORT ===")
print(f"Total samples: {combined_df['sample_id'].nunique()}")
print(f"Total data points: {len(combined_df)}")
print(f"Time range: {combined_df['time'].min():.2f} to {combined_df['time'].max():.2f} seconds")
