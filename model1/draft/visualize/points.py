import plotly.graph_objects as go
import pandas as pd
import numpy as np

flow_df = pd.read_csv('FA#2#F[1].csv')
pressure_df = pd.read_csv('FA#2#F_P[1.2].csv')

print("Flow Data:")
print(flow_df.head())
print(f"\nFlow data shape: {flow_df.shape}")

print("\nPressure Data:")
print(pressure_df.head())
print(f"\nPressure data shape: {pressure_df.shape}")

#merge the scalers in one df
def synchronize_signals(flow_df, pressure_df, method='nearest'):

    # Combine all unique time points
    all_times = np.sort(np.unique(np.concatenate([
        flow_df['time'].values,
        pressure_df['time'].values
    ])))

    # Create new DataFrame with all time points
    synchronized_df = pd.DataFrame({'time': all_times})

    # Interpolate flow and pressure to common time points
    synchronized_df['flow'] = np.interp(
        all_times,
        flow_df['time'].values,
        flow_df['flow'].values
    )

    synchronized_df['pressure'] = np.interp(
        all_times,
        pressure_df['time'].values,
        pressure_df['pressure'].values
    )

    # Add anomaly labels
    synchronized_df['anomaly'] = 1
    synchronized_df['anomaly_type'] = 'flow asynchrony'
    synchronized_df['sample_id'] = 1

    return synchronized_df


# Synchronize the data
df = synchronize_signals(flow_df, pressure_df)

print("\nCombined Data:")
print(df.head())
print(f"\nCombined data shape: {df.shape}")

# Save the labeled dataset
df.to_csv('FA_S1.csv', index=False)
