**Karyalay ERP: CRM Tasks Section – Functional Specification Document**

---

### ✅ Module Name: CRM – Tasks Section
The **Tasks Section** serves as the operational execution layer of the CRM module in Karyalay ERP. It allows employees and managers to create, assign, and track actionable items that arise from leads or follow-up activities. Each task ensures accountability, progress tracking, and closure transparency.

This section connects with **Leads**, **Calls**, **Meetings**, and **Visits**, representing the final actionable stage of CRM workflows.

---

## 📆 1. Functional Overview
The Tasks module allows:
- Creation and assignment of work tasks to employees
- Linking of tasks to leads for traceability
- Setting due dates, priorities, and completion tracking
- Adding closing notes and attachments as proof of work
- Scheduling future follow-ups based on completion
- Visibility in the shared CRM Calendar

---

## ✨ 2. Feature List

### Employee Side:
- View assigned and self-created tasks
- Update progress or mark tasks as complete
- Upload attachments or images as proof
- Add remarks or completion notes
- View upcoming and overdue tasks in calendar

### Manager/Admin Side:
- Assign new tasks to employees manually or via follow-up creation
- Reassign or edit task details and due dates
- Track completion rate by employee or department
- Export reports for completed, pending, and overdue tasks

---

## 🧮 3. Database Structure Reference (from Schema Doc)
**Table:** `crm_tasks`

| Field            | Type            | Description                                         |
|------------------|-----------------|-----------------------------------------------------|
| id               | INT, PK, AI      | Unique task ID                                      |
| lead_id          | INT, FK NULL     | Optional linked lead                                |
| title            | VARCHAR(150)     | Task title or short description                     |
| description      | TEXT             | Detailed explanation of task                        |
| status           | ENUM             | Pending / In Progress / Completed                   |
| due_date         | DATE             | Task deadline                                       |
| completion_notes | TEXT NULL        | Notes added when task is marked complete            |
| completed_at     | DATETIME NULL    | Time when task was completed                        |
| closed_by        | INT, FK NULL     | Employee who closed the task                        |
| follow_up_date   | DATE NULL        | Next scheduled follow-up (optional)                 |
| follow_up_type   | ENUM NULL        | Call / Meeting / Visit / Task                       |
| created_by       | INT, FK          | Creator of the task                                 |
| assigned_to      | INT, FK          | Responsible employee                                |
| location         | TEXT             | Optional GPS or address                             |
| attachment       | TEXT             | File path or uploaded proof                         |
| created_at       | TIMESTAMP        | Creation timestamp                                  |
| updated_at       | TIMESTAMP NULL   | Update timestamp                                   |

---

## 🔗 4. Relationship with Other CRM Modules
| Related Module | Relationship Type | Purpose |
|----------------|-------------------|----------|
| Leads | Many-to-One | Tasks linked to leads represent actionable items toward conversion |
| Calls | Optional Link | Tasks may be created as outcomes of calls |
| Meetings | Optional Link | Tasks can originate from meeting discussions |
| Visits | Optional Link | Tasks may be created after field visits |

Each task updates `last_contacted_at` in the associated lead when marked complete.

---

## 🧱 5. Frontend Pages
| Page | URL Route | Description | Access Role |
|------|------------|-------------|--------------|
| All Tasks | `/crm/tasks` | View all created tasks | Admin, Manager |
| My Tasks | `/crm/tasks/my` | View assigned or self-created tasks | Employee |
| Add Task | `/crm/tasks/add` | Create new task | Employee, Manager |
| Edit Task | `/crm/tasks/edit/:id` | Modify existing task | Manager, Admin |
| Task Details | `/crm/tasks/view/:id` | View detailed task info and completion notes | All roles |

---

## 🚀 6. Backend Routes (PHP Endpoints)
| Method | Route | Purpose | Auth |
|--------|--------|----------|-------|
| GET | `/api/crm/tasks` | Fetch all tasks | Yes |
| GET | `/api/crm/tasks/:id` | Fetch single task | Yes |
| POST | `/api/crm/tasks/add` | Create new task | Yes |
| POST | `/api/crm/tasks/update/:id` | Edit or mark complete | Yes |
| DELETE | `/api/crm/tasks/delete/:id` | Soft delete a task | Admin |

---

## ⚙️ 7. Validations & Rules
- Task title and due date are mandatory
- Status changes must follow valid transitions (Pending → In Progress → Completed)
- `lead_id` must exist if linked
- Completion notes required when marking as completed
- Attachments allowed: PDF, JPG, PNG (max 5MB)

---

## 📧 8. Notifications & Calendar Integration
| Trigger | Type | Recipient | Message |
|----------|------|------------|----------|
| New task assigned | WhatsApp | Assigned employee | You’ve been assigned a new task: [Task Title] |
| Task overdue | Email | Assigned employee | Reminder: Task [Task Title] is overdue |
| Task completed | Email | Creator | Task [Task Title] has been marked as complete |

Tasks appear in the CRM Calendar with **blue color coding** and show status indicators (Pending, In Progress, Completed).

---

## 📈 9. Reports & Exports
- Task completion rate per employee or department
- Task aging report (pending >7 days, >15 days)
- Follow-up conversion effectiveness
- Export filtered tasks (by date/status/employee)

---

## ⏳ 10. Future Enhancements (Phase 2)
- Task priority and tagging (High/Medium/Low)
- Recurring task scheduling
- Time-tracking per task (work logs)
- Task dependencies and milestone-based workflows

---

This document provides the full functional, structural, and operational details of the **Tasks Section** under the CRM module of Karyalay ERP.