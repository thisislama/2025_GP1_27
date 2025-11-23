# try3_split_by_wavecount.py
from pathlib import Path
import numpy as np
import pandas as pd
import plotly.graph_objects as go
from plotly.subplots import make_subplots

# <<< عدّلي المسار على ملفك الموحّد بعد المعالجة >>>
CSV_PATH = Path("digitizewaves/margeAllAfterPreprocess.csv")
# لو ترسمين من ملف ثاني، عدّلي السطر أعلاه

# ---------- قراءة الملف الصارم ----------
def load_df(path):
    df = pd.read_csv(path)
    df.columns = [c.strip().lower() for c in df.columns]

    # تحويل الأعمدة الرقمية فقط
    for col in ["volume","flow","paw","pressure","time","wave_count"]:
        if col in df.columns:
            df[col] = pd.to_numeric(df[col], errors="coerce")

    # alias للضغط
    if "paw" not in df.columns and "pressure" in df.columns:
        df = df.rename(columns={"pressure":"paw"})

    # تأكدنا من وجود wave_count
    if "wave_count" not in df.columns:
        raise ValueError("الملف لا يحتوي على عمود wave_count. تأكد أنك تستخدم ملف margeAllAfter.csv الناتج من التقسيم.")

    return df

# ---------- إعادة أخذ العينات + تمليس (للرسم فقط) ----------
def resample_and_smooth(one, dt=None, smooth_win=7):
    one = one.dropna(subset=["time"]).sort_values("time")

    # إذا ما أعطينا dt، قدّرناه من الداتا نفسها
    if dt is None:
        tvals = one["time"].to_numpy()
        diffs = np.diff(tvals)
        diffs = diffs[np.isfinite(diffs) & (diffs > 0)]
        dt = float(np.median(diffs)) if diffssize(diffs:=diffs).sum() else 0.02  # fallback

    tmin, tmax = float(one["time"].min()), float(one["time"].max())
    # شبكة منتظمة
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

    # تنسيقات عامة
    fig.update_layout(
        title=dict(text=f"VENTILATOR MONITOR - wave_count={sid}", x=0.5,
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

# --------- تشغيل ----------
df = load_df(CSV_PATH)

# نستخدم wave_count كمُعرّف للرسم
id_col = "wave_count"

# نرتّب القيم تصاعدياً (ويتأكد أنها رقمية)
unique_ids = sorted(pd.to_numeric(df[id_col], errors="coerce").dropna().astype(int).unique())

for wid in unique_ids:
    # كل نافذة لها نفس wave_count (1200 صف غالباً)
    cols = ["time","volume","flow","paw"]
    cols = [c for c in cols if c in df.columns]
    one = df.loc[df[id_col] == wid, cols]

    # تقدير خطوة الزمن لهذه النافذة (غالباً ثابتة 0..12s بخطوة 1/SAMPLE_RATE)
    if "time" in one.columns and len(one) > 2:
        diffs = np.diff(one["time"].to_numpy())
        diffs = diffs[np.isfinite(diffs) & (diffs > 0)]
        dt_guess = float(np.median(diffs)) if diffs.size else 0.02
    else:
        dt_guess = 0.02

    one_r = resample_and_smooth(one, dt=dt_guess, smooth_win=7)
    fig = make_ventilator_fig(one_r, wid)
    fig.show()
    # اختياري: حفظ كل نافذة كـ HTML
    # fig.write_html(f"vent_wavecount_{wid:05d}.html")
