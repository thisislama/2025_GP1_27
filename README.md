# TANAFS – Ventilator Waveform Anomaly Detection
<p align="center">
  <img src="images/tanafspic.jpg" alt="TANAFS"/>
</p>

##  Introduction
This project addresses the challenge of manually monitoring and interpreting ventilator waveforms, 
a process that is mentally demanding, and heavily reliant on the experience of medical staff. So we propose the development 
of an intelligent automated detection system that analyzes ventilator waveform images. To overcome the critical challenges in manual ventilator waveform monitoring.

##  Technology Stack
 #### All software tools/technology will be used for this project:
Backend & AI
<p> <img src="raw.githubusercontent.com" width="55" title="Django Logo"/> <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/tensorflow/tensorflow-original.svg" width="55" title="TensorFlow"/> <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/pytorch/pytorch-original.svg" width="55" title="PyTorch"/> <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/opencv/opencv-original.svg" width="55" title="OpenCV"/>  </p>
Frontend
<p> <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/html5/html5-original.svg" width="50" title="HTML5"/> <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/css3/css3-original.svg" width="50" title="CSS3"/> <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/javascript/javascript-original.svg" width="50" title="JavaScript"/> </p>
Database & Hosting
<p> <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/mysql/mysql-original.svg" width="55" title="MySQL"/>  <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/flask/flask-original.svg" width="55" title="Flask"/></p>
Development Tools
<p> <img src="https://img.shields.io/badge/Google%20Colab-F9AB00?style=for-the-badge&logo=googlecolab&logoColor=black" height="32" title="Google Colab"/> <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/vscode/vscode-original.svg" width="50" title="VS Code"/> </p>

---
## Installation Instructions

### Prerequisites

| Requirement | Version / Specification |
| :--- | :--- |
| Web Server | Apache (XAMPP / WAMP / MAMP / Live Server) |
| PHP | 7.4 or higher |
| MySQL | 5.7 or higher |
| Python | 3.8 or higher |
| Browser | Microsoft Edge (recommended), Firefox, Safari |

---

### Step 1: Clone the Repository

```bash
git clone https://github.com/thisislama/2025_GP1_27.git
cd 2025_GP1_27
```

---

### Step 2: Configure the Patient Management System (PMS) Data Source

The system uses a simulated PMS JSON file for patient records.

1. Ensure the file exists at:
   ```
   data/patients_record.json
   ```
2. The JSON structure should follow this format:

```json
{
  "hospital_records": [
    {
      "PID": "P-1001",
      "first_name": "Ali",
      "last_name": "AlHarbi",
      "gender": "Male",
      "status": "active",
      "phone": "05XXXXXXXX",
      "DOB": "1990-03-14"
    }
  ]
}
```

---

### Step 3: Set Up the AI Model (Python / FastAPI)

1. Navigate to the model directory:
   ```bash
   cd model1
   ```
   
2. Create a virtual environment:
   ```bash
   python -m venv venv
   ```
   activate it by:
    ```bash
   venv\Scripts\activate
   ```
   
3. Install Python dependencies:
   ```bash
   pip install fastapi uvicorn torch torchvision pillow python-multipart
   ```

4. Run the FastAPI server:
   ```bash
     uvicorn main:app --reload
   ```
 

   

The AI API will be available at: `http://localhost:8000`

---

### Live Server (Hostinger)

The application is hosted at:
```
https://skyblue-whale-909440.hostingersite.com/
```

---

## Testing Information

### Test User Credentials

| Role | Email | Password |
| :--- | :--- | :--- |
| Respiratory Therapist | rt@tanafs.com | Password123! |


> **Note:** You can also create a new account via the Sign-Up page. Email verification is required.

---

### Test Patient Data (for PMS Simulation)

| File Number (PID) | Name | Status |
| :--- | :--- | :--- |
| P-1001 | Ali AlHarbi | active |
| P-1010 | Lama AlShahrani | active |
| P-1011 | Hassan AlMalki | active |

---

### Test Waveform Images

Sample waveform images for testing are available in the `sample-images/` directory:

| File | Expected Result |
| :--- | :--- |
| `normal-flow.jpg` | Normal Flow |
| `double-triggering.jpg` | Double Triggering Flow |
| `accumulation.jpg` | Accumulation Flow |
| `leakage.jpg` | Leakage Volume |

---

### AI Model Performance

| Metric | Value |
| :--- | :--- |
| Architecture | ResNet-18 |
| Training Accuracy | 94% |
| Test Accuracy | 93.93% |
| Weighted F1-Score | 0.94 |
| Categories | 12 waveform types |
| Avg Response Time | 6.3 seconds |

---

### System Availability Monitoring

| Tool | UptimeRobot |
| :--- | :--- |
| Monitoring Interval | Every 5 minutes |
| Measured Availability | 99.8% |
| Monitoring Period | May 3-9, 2025 |

---

## Project Structure

```
2025_GP1_27/
│
├── frontend/
│   ├── dashboard.php
│   ├── patients.php
│   ├── history2.php
│   ├── profile.php
│   ├── signin.php
│   ├── signup.php
│   ├── dashboard-style.css
│   ├── styles.css
│   └── main.js
│
├── backend/
│   ├── db_connection.php
│   ├── Logout.php
│   └── PHPMailer/
│
├── model1/
│   ├── Train_ResNet_W - Copy.ipynb
│   ├── app.py (FastAPI)
│   └── requirements.txt
│
├── database/
│   └── tanafs_db.sql
│
├── data/
│   └── patients_record.json
│
├── images/
│   └── (logo, icons, etc.)
│
├── js/
│   └── tanafs-shortcuts.js
│
├── sample-images/
│   └── (test waveform images)
│
└── README.md
```

---

## GitHub Repository

| Link | URL |
| :--- | :--- |
| **Repository** | https://github.com/thisislama/2025_GP1_27 |
| **AI Model Notebook** | https://github.com/thisislama/2025_GP1_27/blob/main/model1/Train_ResNet_W%20-%20Copy.ipynb |

---

## Jira Project Management

| Link | URL |
| :--- | :--- |
| **Jira Board** | https://gp1447g27.atlassian.net/jira/software/projects/GP/boards/1 |


---

## License

© 2026 TANAFS Company. All rights reserved.

---

**TANAFS | تنفس** 
–--
Breathe well, live well
