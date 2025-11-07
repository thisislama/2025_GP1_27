# try3_split_by_id.py
from pathlib import Path
import numpy as np
import pandas as pd
import plotly.graph_objects as go
from plotly.subplots import make_subplots

CSV_PATH = Path(r"C:\Tanafas_Dataset\digitizewaves\margeAll.csv")

# ---------- قراءة الملف الصارم ----------
def load_df(path):
    df = pd.read_csv(path)
    df.columns = [c.strip().lower() for c in df.columns]

    # تحويل الأعمدة الرقمية فقط
    for col in ["volume","flow","paw","pressure","time"]:
        if col in df.columns:
            df[col] = pd.to_numeric(df[col], errors="coerce")

    # alias للضغط
    if "paw" not in df.columns and "pressure" in df.columns:
        df = df.rename(columns={"pressure":"paw"})

    return df

# ---------- إعادة أخذ العينات + تمليس (للرسم فقط) ----------
def resample_and_smooth(one, dt=0.02, smooth_win=7):
    one = one.dropna(subset=["time"]).sort_values("time")
    tmin, tmax = float(one["time"].min()), float(one["time"].max())
    grid = np.arange(tmin, tmax + dt/2, dt)
    grid_idx = pd.Index(grid, name="time")

    def on_grid(col):
        if col not in one.columns:
            return None
        s = one[["time", col]].dropna().set_index("time")[col]
        if s.index.has_duplicates:
            s = s.groupby(level=0).mean()
        s = s.sort_index()
        s = s.reindex(s.index.union(grid_idx)).interpolate("index").reindex(grid_idx)
        if smooth_win and smooth_win >= 3 and smooth_win % 2 == 1:
            s = s.rolling(smooth_win, center=True, min_periods=1).mean()
        return s

    vol = on_grid("volume")
    flo = on_grid("flow")
    paw = on_grid("paw")

    out = pd.DataFrame({"time": grid})
    if vol is not None: out["volume"] = vol.values
    if flo is not None: out["flow"]   = flo.values
    if paw is not None: out["paw"]    = paw.values
    return out

def make_ventilator_fig(sample, sid):
    rows = []
    if "paw" in sample.columns:    rows.append(("paw","PRESSURE","#FF4444", True))
    if "flow" in sample.columns:   rows.append(("flow","FLOW","#44FF44",  False))
    if "volume" in sample.columns: rows.append(("volume","VOLUME","#4444FF", True))

    fig = make_subplots(
        rows=len(rows), cols=1,
        subplot_titles=[r[1] for r in rows],
        vertical_spacing=0.08,
        shared_xaxes=True,
        row_heights=[0.4 if r[0]=="paw" else 0.3 for r in rows]
    )

    for i,(name,title,color,fill_under) in enumerate(rows, start=1):
        x = sample["time"]; y = sample[name]
        fig.add_trace(
            go.Scatter(
                x=x, y=y, mode="lines", name=title,
                line=dict(color=color, width=4 if name=="paw" else 3),
                fill="tozeroy" if fill_under else None,
                fillcolor="rgba(255,68,68,0.10)" if name=="paw"
                          else ("rgba(68,68,255,0.10)" if name=="volume" else None)
            ),
            row=i, col=1
        )
        if name=="flow":
            fig.add_trace(
                go.Scatter(
                    x=[x.min(), x.max()], y=[0,0], mode="lines",
                    line=dict(color="white", width=2), opacity=0.8, showlegend=False
                ),
                row=i, col=1
            )

    # تنسيقات عامة (بدون range ثابت -> autorange تلقائي)
    fig.update_layout(
        title=dict(text=f"VENTILATOR MONITOR - {sid}", x=0.5,
                   font=dict(size=24, color='white', family='Arial Black')),
        height=800, width=1200,
        plot_bgcolor='black', paper_bgcolor='black',
        font=dict(color='white', size=12), showlegend=False,
        margin=dict(t=100, b=50, l=50, r=50)
    )
    fig.update_xaxes(showgrid=True, gridwidth=1, gridcolor='gray',
                     showline=True, linewidth=2, linecolor='white',
                     tickfont=dict(color='white', size=10))
    fig.update_yaxes(showgrid=True, gridwidth=1, gridcolor='gray',
                     showline=True, linewidth=2, linecolor='white',
                     tickfont=dict(color='white', size=10))
    return fig

def find_id_column(df):
    candidates = ["sample_id","id","wave_id","label","wave","name","case","group"]
    for c in candidates:
        if c in df.columns:
            return c
    return None

# --------- تشغيل ----------
df = load_df(CSV_PATH)
id_col = find_id_column(df)

if id_col:
    # نرسم لكل ID شكل مستقل
    for sid in df[id_col].dropna().astype(str).unique():
        one = df[df[id_col].astype(str) == sid][["time","volume","flow","paw"]]
        one_r = resample_and_smooth(one, dt=0.02, smooth_win=7)
        fig = make_ventilator_fig(one_r, sid)
        fig.show()
        # لو حابة تحفظين كل رسمة كصفحة HTML:
        # fig.write_html(f"vent_{sid}.html")
else:
    # بدون عمود هوية -> موجة واحدة
    one = df[["time","volume","flow","paw"]]
    one_r = resample_and_smooth(one, dt=0.02, smooth_win=7)
    fig = make_ventilator_fig(one_r, "N/A")
    fig.show()
