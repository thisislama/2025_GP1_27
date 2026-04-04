
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TANAFS Patient Summary Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root{
            --primary:#0d6fb8;
            --primary-dark:#0b4d86;
            --secondary:#19a0d8;
            --text:#1d2433;
            --muted:#6b7280;
            --border:#d9e2ec;
            --soft:#f7fbff;
            --success:#dff5e8;
            --success-text:#18794e;
            --danger:#fde7e9;
            --danger-text:#b42318;
        }

        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            color:var(--text);
            background:#eef3f8;
            font-size:13px; 
        }

        .page-shell{
            padding:30px 18px 60px;
        }

        .toolbar{
            max-width:1000px;
            margin:0 auto 18px;
            display:flex;
            justify-content:flex-end;
            gap:12px;
            flex-wrap:wrap;
        }

        .toolbar button{
            border:none;
            padding:12px 18px;
            border-radius:10px;
            cursor:pointer;
            font-size:14px;
            font-weight:600;
        }

        .toolbar{
    max-width:1000px;
    margin:0 auto 20px;
    display:flex;
    justify-content:flex-end;
    gap:10px;
}

/* زر الرجوع */
.btn-light{
    background:#fff;
    color:#0577fa;
    border:2px solid #0577fa;
    padding:10px 20px;
    border-radius:8px;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    transition: all 0.3s ease;
}

/* hover */
.btn-light:hover{
    background:#0577fa;
    color:#fff;
    box-shadow:0 4px 10px rgba(5,119,250,0.3);
}

/* زر الطباعة */
.btn-primary{
    background:#0577fa;
    color:#fff;
    border:none;
    padding:10px 22px;
    border-radius:8px;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    transition: all 0.3s ease;
}

/* hover */
.btn-primary:hover{
    background:#035fd1;
    box-shadow:0 6px 14px rgba(5,119,250,0.4);
    transform: translateY(-1px);
}

        .btn-light{
            background:#fff;
            color:var(--primary-dark);
            border:1px solid #cbd5e1;
        }

        .report-page{
            position:relative;
            width:100%;
            max-width:1000px;
            margin:0 auto;
            background:#fff;
            min-height:1400px;
            box-shadow:0 16px 40px rgba(15, 76, 129, .12);
            overflow:hidden;
        }

        .top-band{
            height:70px;
    background:#0577fa; 
            position:relative;
        }

        .top-band::before,
        .top-band::after{
            content:"";
            position:absolute;
            top:0;
            width:180px;
            height:90px;
            opacity:.18;
            transform:skew(-35deg);
            background:#fff;
        }

        .top-band::before{
            left:-70px;
        }

        .top-band::after{
            left:60px;
        }

        .report-inner{
            position:relative;
        padding: 20px 30px 100px; 

        }
        .info-grid .value {
 color:#111827;
    max-width: 100%;
    word-break: break-word;    overflow-wrap: break-word;
}

        .brand-row{
    display:flex;
    align-items:flex-start; 
    margin-bottom:20px;
    justify-content:flex-start;
}

        .brand-left{
            display:flex;
            align-items:center;
            gap:16px;
            margin-left: -10px;
        }

        .brand-mark{
            width:72px;
            height:72px;
            border-radius:18px;
            background:linear-gradient(180deg, #e7f5ff, #d7ecff);
            display:flex;
            align-items:center;
            justify-content:center;
            color:var(--primary-dark);
            font-size:34px;
            font-weight:700;
            border:1px solid #d0e4f6;
        }

        .brand-name h1{
            margin:0;
            font-size:28px;
            color:var(--primary-dark);
            letter-spacing:.4px;
                color:#0577fa;

        }

        .brand-name p{
            margin:6px 0 0;
            color:var(--muted);
            font-size:14px;
        }

        .brand-right{
            text-align:right;
        }

        .brand-right .system-name{
            font-size:44px;
            font-weight:800;
            color:#166cc1;
            line-height:1;
            letter-spacing:.5px;
        }

        .brand-right .system-sub{
            margin-top:8px;
            color:var(--muted);
            font-size:14px;
        }

        .document-title{
            text-align: center;
    margin: 30px auto;
        }

        .document-title h2{
            margin:0;
            font-size:28px;
            letter-spacing:1px;
            color:#111827;
        }

        .document-title p{
            margin:10px 0 0;
            color:var(--muted);
            font-size:15px;
        }

.info-grid {
    display: grid;
    grid-template-columns: 180px 1fr;
    row-gap: 12px;
    column-gap: 12px;
}
.info-card {
    width: 100%;   
    max-width: 750px; 
    margin: 0 auto; 
    background:#fff;
    border:1px solid var(--border);
    border-radius:16px;
    padding:22px;
}
.full-section {
    max-width: 750px;
    margin: 22px auto; 
}

        .section-title{
            margin:0 0 16px;
            color:var(--primary-dark);
            font-size:21px;
            font-weight:800;
        }

        .info-grid{
            display:grid;
            grid-template-columns:160px 1fr;
            row-gap:12px;
            column-gap:10px;
            font-size:15px;
        }

        .info-grid .label{
            font-weight:700;
            color:#243b53;
        }

        .info-grid .value{
            color:#111827;
        }

        .full-section{
            margin-top:22px;
            border:1px solid var(--border);
            border-radius:16px;
            padding:24px;
            background:#fff;
        }

        .summary-box{
            background:linear-gradient(180deg, #f8fcff 0%, #f5f9fd 100%);
        }

        .summary-box p{
            margin:0;
            font-size:16px;
            line-height:1.9;
            color:#2f3e4e;
        }

        .stats-row{
            display:grid;
            grid-template-columns:repeat(4, 1fr);
            gap:14px;
            margin-top:18px;
        }

        .stat{
            border:1px solid #dbe7f1;
            border-radius:14px;
            padding:16px;
            background:#fff;
        }

        .stat .num{
            font-size:28px;
            font-weight:800;
            color:var(--primary-dark);
        }

        .stat .caption{
            margin-top:6px;
            font-size:13px;
            color:var(--muted);
        }

        .list-cards{
            display:flex;
            flex-direction:column;
            gap:14px;
        }

        .timeline-card{
            border:1px solid var(--border);
            border-radius:14px;
            padding:16px 18px;
            background:#fff;
        }

        .timeline-top{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:14px;
            margin-bottom:10px;
        }

        .timeline-top h4{
            margin:0;
            font-size:17px;
            color:#102a43;
        }

        .timeline-top p{
            margin:4px 0 0;
            color:var(--muted);
            font-size:13px;
        }

        .badge{
            padding:7px 12px;
            border-radius:999px;
            font-size:12px;
            font-weight:800;
            letter-spacing:.3px;
            white-space:nowrap;
        }

        .badge.normal{
            background:var(--success);
            color:var(--success-text);
        }

        .badge.anomaly{
            background:var(--danger);
            color:var(--danger-text);
        }

        .timeline-details{
            display:grid;
            grid-template-columns:180px 1fr;
            row-gap:10px;
            column-gap:10px;
            font-size:14px;
        }

        .timeline-details .label{
            font-weight:700;
            color:#334e68;
        }

        .timeline-details .value{
            color:#1f2937;
        }

        .recommendation-list,
        .professional-list{
            margin:0;
            padding-left:22px;
        }

        .recommendation-list li,
        .professional-list li{
            margin-bottom:10px;
            line-height:1.8;
        }

        .comment-box{
            border-left:4px solid #89b8e1;
            background:#fbfdff;
            border:1px solid #e5eef7;
            border-left-width:4px;
            border-radius:12px;
            padding:14px 16px;
        }

        .comment-meta{
            font-size:13px;
            color:var(--muted);
            margin-bottom:8px;
            font-weight:600;
        }

        .comment-text{
            line-height:1.9;
            color:#1f2937;
            white-space:pre-wrap;
            word-break:break-word;
        }

        .signature-block{
            margin-top:32px;
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:20px;
        }

        .sign-card{
            border:1px solid var(--border);
            border-radius:16px;
            padding:20px;
            min-height:130px;
            background:#fff;
        }

        .sign-card h4{
            margin:0 0 12px;
            color:var(--primary-dark);
            font-size:18px;
        }

        .sign-card p{
            margin:6px 0;
            line-height:1.8;
        }

        .footer-band{
            position:absolute;
            bottom:0;
            left:0;
            width:100%;
            height:76px;
    background:#0577fa; /* 👈 اللون الجديد */
            color:#fff;
            display:flex;
            justify-content:center;
            align-items:center;
            font-weight:700;
            letter-spacing:2px;
            font-size:16px;
        }

        .watermark{
            position:absolute;
            left:50%;
            top:58%;
            transform:translate(-50%, -50%);
            font-size:280px;
            font-weight:800;
            color:#0d6fb8;
            opacity:.04;
            pointer-events:none;
            user-select:none;
            line-height:1;
        }

        .print-only{
            display:none;
        }

        @media (max-width: 860px){
            .report-inner{
                padding:30px 24px 120px;
            }

            .brand-row,
            .intro-grid,
            .signature-block,
            .stats-row{
                grid-template-columns:1fr;
                display:grid;
            }

            .brand-row{
                gap:18px;
            }

            .brand-right{
                text-align:left;
            }

            .info-grid,
            .timeline-details{
                grid-template-columns:1fr 1fr;
            }
        }

        @media print{
            body{
                background:#fff;
            }

            .page-shell{
                padding:0;
            }

            .toolbar{
                display:none !important;
            }

            .report-page{
                box-shadow:none;
                max-width:none;
                width:100%;
                min-height:auto;
                margin:0;
            }

            .report-inner{
                padding:34px 42px 110px;
            }

            .footer-band{
                position:fixed;
                bottom:0;
            }

            .print-only{
                display:block;
            }
        }

        .report-page {
    max-width: 794px; /* عرض A4 */
}
        @media print {

  .full-section,
  .info-card,
  .timeline-card,
  .comment-box {
      page-break-inside: avoid;
      break-inside: avoid;
  }

  .report-page {
      page-break-after: always;
  }
  .stats-row {
      display: flex !important;
      justify-content: space-between;
      gap: 10px;
  }

  .stat {
      width: 23%;
  }
    .top-band {
      position: relative;
      display: none !important;
  }

  .footer-band {
      position: fixed;
      bottom: 0;
      left: 0;
  }
    @page {
      size: A4;
      margin: 20mm;
  }
}
    </style>
</head>
<body>
<div class="page-shell">

    <div class="toolbar">
        <button class="btn-light" onclick="window.history.back()">Back</button>
        <button class="btn-primary" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="report-page" id="reportPage">
        <div class="top-band"></div>
        <div class="watermark">T</div>

        <div class="report-inner">
            <div class="brand-row">
             

                <div class="brand-left">
<img src="images/Logo.png" alt="TANAFS Logo" style="width:140px; height:auto;">
    <div class="brand-name">
        <h1>TANAFS</h1>
        <p>Patient Summary & Clinical Review Report</p>
    </div>
</div>
            </div>

            <div class="document-title">
                <h2>MEDICAL SUMMARY REPORT</h2>
                <p>Generated on <?= e(date('d M Y, h:i A')); ?></p>
            </div>

            <div class="intro-grid">
                <div class="info-card">
                    <h3 class="section-title">Patient Information</h3>
                    <div class="info-grid">
                        <div class="label">Patient Name:</div>
                        <div class="value"><?= e($patient['first_name'] . ' ' . $patient['last_name']); ?></div>

                        <div class="label">Patient ID:</div>
                        <div class="value"><?= e($patient['PID']); ?></div>

                        <div class="label">Gender:</div>
                        <div class="value"><?= e($patient['gender']); ?></div>

                        <div class="label">Date of Birth:</div>
                        <div class="value"><?= e(formatDateValue($patient['DOB'])); ?></div>

                        <div class="label">Phone:</div>
                        <div class="value"><?= e($patient['phone'] ?: '-'); ?></div>

                        <div class="label">Status:</div>
                        <div class="value"><?= e(ucfirst($patient['status'] ?: '-')); ?></div>
                    </div>
                </div>

                <div class="info-card">
                    <h3 class="section-title">Generated By</h3>
                    <div class="info-grid">
                        <div class="label">Healthcare Professional:</div>
                        <div class="value"><?= e($doctorName); ?></div>

                        <div class="label">Role:</div>
                        <div class="value"><?= e($doctorRole); ?></div>

                        <div class="label">Email:</div>
                        <div class="value"><?= e($doctorEmail); ?></div>

                        <div class="label">Phone:</div>
                        <div class="value"><?= e($doctorPhone); ?></div>

                        <div class="label">Report Date:</div>
                        <div class="value"><?= e(date('d M Y')); ?></div>

                        <div class="label">Report Time:</div>
                        <div class="value"><?= e(date('h:i A')); ?></div>
                    </div>
                </div>
            </div>

            <div class="full-section summary-box">
                <h3 class="section-title">Clinical Summary</h3>
                <p style="font-size:15px; line-height:2; color:#2c3e50;">
    <?= e($summaryText); ?>
</p>

                <div class="stats-row">
                    <div class="stat">
                        <div class="num"><?= e($totalAnalyses); ?></div>
                        <div class="caption">Total Analyses</div>
                    </div>

                    <div class="stat">
                        <div class="num"><?= e($totalAnomalies); ?></div>
                        <div class="caption">Detected Anomalies</div>
                    </div>

                    <div class="stat">
                        <div class="num"><?= e($totalComments); ?></div>
                        <div class="caption">Clinical Comments</div>
                    </div>

                    <div class="stat">
                        <div class="num"><?= e($topAnomaly); ?></div>
                        <div class="caption">Most Frequent Pattern</div>
                    </div>
                </div>
            </div>

            <div class="full-section">
                <h3 class="section-title">All Previous Waveform Analyses</h3>

                <div class="list-cards">
                    <?php foreach ($waves as $wave): ?>
                        <?php
                            $statusClass = ($wave['status'] === 'anomaly') ? 'anomaly' : 'normal';
                            $statusText = strtoupper($wave['status'] ?: 'normal');
                            $performedBy = trim(($wave['hp_first_name'] ?? '') . ' ' . ($wave['hp_last_name'] ?? ''));
                            if ($performedBy === '') $performedBy = 'Unknown';
                            $anomalyText = cleanAnomalyLabel($wave['anomaly_type'] ?? '');
                            $notesText = trim((string)($wave['finding_notes'] ?? ''));
                        ?>
                        <div class="timeline-card">
                            <div class="timeline-top">
                                <div>
                                    <h4><?= e($anomalyText); ?></h4>
                                    <p>Recorded on <?= e(formatDateTimeValue($wave['timestamp'])); ?></p>
                                </div>
                                <span class="badge <?= e($statusClass); ?>"><?= e($statusText); ?></span>
                            </div>

                            <div class="timeline-details">
                                <div class="label">Performed by:</div>
                                <div class="value"><?= e($performedBy); ?><?= !empty($wave['hp_role']) ? ' (' . e($wave['hp_role']) . ')' : ''; ?></div>


                                <div class="label">Anomaly Type:</div>
                                <div class="value"><?= e($anomalyText); ?></div>

                                <div class="label">Doctor Action:</div>
                                <div class="value"><?= e($notesText !== '' ? $notesText : 'No additional Doctor Action were documented for this analysis.'); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="full-section">
                <h3 class="section-title">Healthcare Professionals' Comments</h3>

                <div class="list-cards">
                    <?php foreach ($comments as $comment): ?>
                        <?php
                            $commentAuthor = trim(($comment['first_name'] ?? '') . ' ' . ($comment['last_name'] ?? ''));
                            if ($commentAuthor === '') $commentAuthor = 'Unknown';
                        ?>
                        <div class="comment-box">
                            <div class="comment-meta">
                                <?= e($commentAuthor); ?>
                                <?= !empty($comment['role']) ? ' - ' . e($comment['role']) : ''; ?>
                                | <?= e(formatDateTimeValue($comment['timestamp'])); ?>
                            </div>
                            <div class="comment-text"><?= e($comment['content']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="full-section">
                <h3 class="section-title">Key Recommendations</h3>
                <ul class="recommendation-list">
                    <?php foreach ($recommendations as $recommendation): ?>
                        <li><?= e($recommendation); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

      
        </div>

        <div class="footer-band">
            TANAFS • PATIENT SUMMARY REPORT
        </div>
    </div>
</div>
</body>
</html>
