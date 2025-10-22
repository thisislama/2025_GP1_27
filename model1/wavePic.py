import pandas as pd
import numpy as np
import matplotlib.pyplot as plt


def analyze_middle_segment(file_path, patient_id, start_row=5000, end_row=8000):
    df = pd.read_csv(file_path)

    # Ensure don't exceed dataframe bounds
    start_row = min(start_row, len(df) - 1)
    end_row = min(end_row, len(df))

    middle_segment = df.iloc[start_row:end_row].copy()

  #  print(f"=== Patient {patient_id} - Analysis ===")
  #   print(f"Analyzing rows {start_row} to {end_row} ({len(middle_segment)} points)")
 #    print(f"Time range: {middle_segment['time'].min():.2f} to {middle_segment['time'].max():.2f}")

    # visualization
    signals = ['flow', 'paw', 'vol', 'pmus']

    fig, axes = plt.subplots(2, 2, figsize=(15, 10))
    axes = axes.flatten()

    for i, signal in enumerate(signals):
        if signal in df.columns:
            axes[i].plot(middle_segment['time'], middle_segment[signal], linewidth=1)
            axes[i].set_title(f'Rows {start_row}-{end_row}: {signal}')
            axes[i].set_ylabel(signal)
            axes[i].set_xlabel('Time')
            axes[i].grid(True, alpha=0.3)

    plt.tight_layout()
    plt.show()

    return middle_segment


# Analyze middle segment
patient_file = "dataSample/Run63.csv"
middle_data = analyze_middle_segment(patient_file, 1, 13000, 15000)