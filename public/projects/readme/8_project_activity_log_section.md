**Karyalay ERP: Project Activity Log Section – Functional Specification**

---

### 🧩 Section: Project Activity Log
The **Activity Log Section** in the Projects Module tracks every significant event within a project — including task creation, status changes, phase updates, document uploads, member actions, and general edits. It ensures accountability, traceability, and provides a transparent audit trail for project stakeholders.

Route: `/projects/view/:id#activity`

---

## 🎯 1) Objectives
- Maintain a detailed, time-stamped record of all project-related actions.
- Provide chronological visibility of team activity and progress.
- Enable admins and managers to review project history efficiently.
- Support filtering and export for reporting or compliance.

---

## ⚙️ 2) Key Features
- Automatically record every project-related CRUD action.
- Categorize entries by type (Task, Phase, Member, Document, Status, General).
- Store who performed the action, when, and on which entity.
- Display recent activities in reverse chronological order.
- Support filtering by user, activity type, and date range.
- Allow inline comment or note tagging on key events.
- Integrate with dashboard widgets (e.g., recent project updates).

---

## 🧱 3) UI Components
### A) Activity Timeline View
- Displays actions grouped by day.
- Each item shows:
  - User avatar + name
  - Activity description
  - Timestamp (relative time, e.g., “2 hours ago”)
  - Related entity link (task/phase/doc)
- Color-coded badges for activity types.

### B) Filters Panel
- Filter by: Date Range, User, Activity Type.
- Search by keyword.

### C) Export Button
- Export filtered results to CSV/PDF for reporting.

---

## 🧮 4) Database Reference
Refers to `project_activity_log` from the Projects DB Document.

| Field | Type | Description |
|--------|------|--------------|
| id | INT, PK, AI | Unique log entry ID |
| project_id | INT, FK | Linked project |
| user_id | INT, FK | User performing the action |
| activity_type | ENUM('Task','Phase','Document','Status','Member','General') | Type of event |
| reference_id | INT NULL | Related entity ID |
| description | TEXT | Summary of what occurred |
| created_at | TIMESTAMP | Timestamp of the action |

Additional considerations:
- All project submodules trigger entries upon data mutation.
- A background listener can queue logs for batch insertion to optimize performance.

---

## 🚀 5) Backend Endpoints
| Method | Route | Purpose | Auth |
|---|---|---|---|
| GET | `/api/projects/:id/activity` | Fetch activity log list (filterable) | Yes |
| GET | `/api/projects/:id/activity/:log_id` | Fetch single log entry details | Yes |
| POST | `/api/projects/activity/add` | Add custom/general note (manual entry) | Owner/Admin |
| DELETE | `/api/projects/activity/delete/:log_id` | Delete a log entry (admin only) | Admin |
| GET | `/api/projects/:id/activity/export` | Export filtered activity logs | Yes |

---

## 🔗 6) Integration Points
| Source Module | Trigger Type |
|----------------|--------------|
| **Tasks** | Create/update/delete/status change |
| **Phases** | Create/update/delete/status change |
| **Documents** | Upload/version/delete |
| **Members** | Add/update/remove |
| **Project Core** | Update project details or status |
| **Notebook** | Linked note created or updated (logged as 'General') |

Each integration pushes a concise record to `project_activity_log`.

---

## 🔐 7) Access Control
| Role | View | Add Custom Note | Delete Log |
|---|---|---|---|
| Admin | ✔ | ✔ | ✔ |
| Manager | ✔ | ✔ | ✖ |
| Employee (Member) | ✔ (own project) | ✖ | ✖ |
| Viewer | ✔ (read-only) | ✖ | ✖ |

Unauthorized attempts redirect to `/unauthorized`.

---

## 📊 8) Reporting / Export
- Columns: Date, Time, User, Activity Type, Description.
- Filters: date range, user, activity type.
- Formats: CSV, XLSX, PDF.
- Sorting: newest → oldest.

---

## 🧠 9) Advanced Capabilities (Optional)
- Real-time live activity feed (via WebSocket or polling).
- Smart grouping (e.g., 5 consecutive task updates collapsed into one summarized entry).
- Integration with ERP Dashboard (recent updates widget).
- Inline comment threads for discussions on specific activities.

---

## ⏳ 10) Future Enhancements
- Activity analytics: per-user contribution graphs.
- Export activity summary in project closeout reports.
- AI summary: automatic daily digest of key actions.

---

### ✅ Summary
The **Project Activity Log Section** ensures transparency and accountability across all project actions. With detailed records, filters, and export capabilities, it provides an indispensable audit layer within the Karyalay ERP Projects Module — connecting all task, phase, member, and document operations in one chronological view.

