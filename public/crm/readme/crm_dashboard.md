**Karyalay ERP: CRM Quality Dashboard – Functional Specification Document**

---

### 📊 Module Name: CRM – Quality Dashboard
The **CRM Quality Dashboard** is a performance analytics page designed for the **Managers and Admins** of Karyalay ERP. It provides a consolidated, visual summary of CRM operations across Leads, Calls, Meetings, Visits, and Tasks — helping management evaluate productivity, engagement quality, and follow-up discipline.

This dashboard acts as the analytical control center for the CRM module, accessible at `/crm/dashboard`.

---

## 🧩 1. Purpose
To provide real-time insight into CRM effectiveness and employee engagement through intuitive visual elements, color-coded indicators, and performance metrics.

It enables:
- Quick evaluation of team and individual productivity
- Monitoring of follow-up consistency and conversion efficiency
- Identification of leads or employees needing attention
- Strategic decision-making using summarized CRM data

---

## 🖥️ 2. Page Overview
**URL Route:** `/crm/dashboard`

**Access Roles:** Manager, Admin

**Layout:**
1. Header Bar with filters (Date Range, Employee, Department)
2. KPI Cards Section
3. Charts & Graphs Section
4. Activity Quality Table
5. Follow-up Compliance Matrix
6. Recent Interactions Summary

---

## 📆 3. Filters
| Filter | Type | Description |
|---------|------|--------------|
| Date Range | Date Picker | View performance for a specific time period |
| Employee | Dropdown | Filter data for a single employee |
| Department | Dropdown | Aggregate CRM performance by department |
| Lead Source | Dropdown | Optional filter to evaluate source quality |

---

## ✨ 4. KPI Summary Cards (Top Section)
Each KPI card shows aggregate data with trend indicators (▲▼).

| Metric | Description | Visual Element |
|----------|--------------|----------------|
| **Total Leads** | Total new leads created within selected range | Card + Count + % vs previous period |
| **Active Leads** | Leads in progress (Contacted, Not Converted) | Card + Progress ring |
| **Conversion Rate** | % of leads converted successfully | Card + Green/Red indicator |
| **Follow-up Compliance** | % of completed follow-ups on time | Card + Semi-circle gauge |
| **Avg Response Time** | Time between lead creation and first contact | Card + Clock icon |
| **Pending Tasks** | Number of uncompleted CRM tasks | Card + Count + Red warning if > threshold |

---

## 📈 5. Visual Analytics (Middle Section)
### A. **Lead Flow Funnel (Chart)**
- **Type:** Funnel Chart
- **Stages:** New → Contacted → In Progress → Converted → Dropped
- **Purpose:** Show drop-off and conversion ratios

### B. **Activity Volume Trend (Chart)**
- **Type:** Line Chart (Date vs Count)
- **Data:** Combined activity count (Calls + Meetings + Visits + Tasks)
- **Color Code:** Calls (Orange), Meetings (Green), Visits (Teal), Tasks (Blue)

### C. **Employee Performance Comparison (Chart)**
- **Type:** Horizontal Bar Chart
- **Metrics:** No. of Leads handled, Conversion %, Follow-up completion rate
- **Purpose:** Identify top/bottom performers

---

## 📋 6. Activity Quality Table
| Column | Description |
|---------|--------------|
| Employee | Name of employee |
| Total Leads | Count of leads handled |
| Calls Made | Total calls recorded |
| Meetings Held | Number of meetings logged |
| Visits Done | Number of field visits |
| Tasks Completed | Number of tasks marked done |
| Conversion % | Converted leads / total leads |
| Follow-up Compliance % | Completed follow-ups / scheduled follow-ups |
| Avg Response Time | Average time to contact a new lead |

**Color Indicators:**
- 🔵 Excellent (>85%)  
- 🟡 Average (60–85%)  
- 🔴 Needs Attention (<60%)

---

## 📅 7. Follow-up Compliance Matrix
A grid showing adherence to follow-up schedules.

| Metric | Description |
|----------|--------------|
| **Total Follow-ups Scheduled** | Count from Leads + Calls + Meetings + Visits + Tasks |
| **Completed On-Time** | % of follow-ups done within date |
| **Delayed** | % of follow-ups missed or late |
| **Auto-Generated from Leads** | % of follow-ups automatically created vs manually added |
| **Average Follow-up Gap** | Average number of days between follow-ups per lead |

**Visualization:** Circular progress graphs and red/yellow/green gauges.

---

## 🧾 8. Recent Interactions Summary
Shows a rolling list of recent CRM activities.

| Field | Description |
|--------|--------------|
| Date | When the activity occurred |
| Employee | Who performed it |
| Lead | Linked lead name |
| Type | Activity Type (Call, Meeting, Visit, Task) |
| Outcome | Brief summary or status |
| Next Follow-up | Next planned action/date |

**Purpose:** Real-time visibility of field and internal actions.

---

## ⚙️ 9. Backend Routes (PHP Endpoints)
| Method | Route | Purpose |
|--------|--------|----------|
| GET | `/api/crm/dashboard/stats` | Fetch KPI card metrics |
| GET | `/api/crm/dashboard/trends` | Fetch chart datasets |
| GET | `/api/crm/dashboard/performance` | Fetch employee comparison data |
| GET | `/api/crm/dashboard/recent` | Fetch latest CRM activities |

All routes are secured and visible to roles with `can_view` permission for `/crm/dashboard`.

---

## 🎯 10. Validation & Access Rules
- Only Managers and Admins can access the dashboard.
- Unauthorized users are redirected to `/unauthorized`.
- Filters default to current month and logged-in user’s department.
- Data auto-refreshes every 5 minutes.

---

## 📈 11. Future Enhancements (Phase 2)
- Department-wise leaderboard with badges and ranking
- AI-driven lead quality scoring based on engagement history
- Voice summary reports (text-to-speech)
- Export as snapshot PDF with charts

---

### ✅ Summary
The **CRM Quality Dashboard** transforms raw CRM data into actionable insights. By visualizing performance across leads, calls, meetings, visits, and tasks, it allows management to:
- Detect bottlenecks early,
- Evaluate employee responsiveness,
- Ensure follow-ups are timely,
- And improve overall customer engagement quality.

It serves as the **command center** for the CRM module — practical, insightful, and perfectly aligned with the needs of SMEs using Karyalay ERP.