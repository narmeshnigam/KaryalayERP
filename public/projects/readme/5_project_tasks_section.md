**Karyalay ERP: Project Tasks Section – Functional Specification**

---

### 🧩 Section: Project Tasks
The **Tasks Section** is the operational core of the Projects Module. It manages all actionable items, supports multiple assignees, and directly drives project and phase progress. Tasks can be created manually or imported from reusable templates.

Route: `/projects/view/:id#tasks`

---

## 🎯 1) Objectives
- Provide a structured environment to manage, assign, and track all project tasks.
- Enable collaboration through multiple assignees, status updates, and completion notes.
- Automate progress computation for projects and phases.
- Allow standardized task creation using **templates**.

---

## ⚙️ 2) Key Features

### Core Functionality
- Create, edit, and delete project tasks.
- Assign multiple users per task.
- Link task to a phase (optional).
- Track progress %, priority, and deadlines.
- Add closing notes when marking as completed.
- Import pre-saved task lists from templates.
- Inline updates (status, assignees, due date).

### Advanced Capabilities
- Filter by phase, assignee, status, or priority.
- Bulk actions (mark complete, change status, reassign).
- Auto-update parent phase/project progress.
- Integration with activity log and notifications.

---

## 🧱 3) UI Structure

### A) Task Board View (Kanban)
- Columns by status: Pending, In Progress, Review, Completed.
- Drag-and-drop task movement updates `status` field.

### B) Task Table View (List)
- Columns: Title, Phase, Priority, Due Date, Assignees, Progress %, Status.
- Sorting by priority, due date, or progress.

### C) Add/Edit Task Modal
**Fields:**
- Title (required)
- Description (optional)
- Phase (dropdown, optional)
- Due Date
- Priority (Low/Medium/High/Critical)
- Progress (%)
- Assignees (multi-select from members)
- Status (default: Pending)
- Closing Notes (on completion)

---

## 🧮 4) Database Reference
Referencing `project_tasks` and `project_task_assignees` from the Projects DB document.

**Relationships:**
- Each task → belongs to one project, optional phase.
- Each task → can have multiple assignees.
- Task completion triggers phase and project progress recalculation.

---

## 🚀 5) API Endpoints
| Method | Route | Purpose | Auth |
|---|---|---|---|
| GET | `/api/projects/:id/tasks` | Fetch all project tasks | Yes |
| GET | `/api/projects/task/:id` | Get detailed task info | Yes |
| POST | `/api/projects/task/add` | Create a new task | Owner/Admin |
| POST | `/api/projects/task/update/:id` | Update title, description, priority, due_date, etc. | Owner/Admin |
| POST | `/api/projects/task/status/:id` | Update status or mark complete | Assignee/Owner |
| POST | `/api/projects/task/assign/:id` | Add or update assignees | Owner/Admin |
| DELETE | `/api/projects/task/delete/:id` | Soft delete task | Owner/Admin |
| POST | `/api/projects/task/import-template` | Import from task list template | Owner/Admin |

---

## ⚙️ 6) Functional Rules
- `title` is mandatory and must be unique within a project.
- `due_date` cannot be before project start_date.
- `progress` auto-sets to 100% when status = Completed.
- Only assigned users or project owner can update status.
- Deleting a task unlinks it from phase and logs to `project_activity_log`.
- Tasks from templates replicate `title`, `description`, and `priority` but not `assignees`.

---

## 🔐 7) Role Permissions
| Role | View | Add/Edit | Assign | Delete | Import Templates |
|---|---|---|---|---|---|
| Admin | ✔ | ✔ | ✔ | ✔ | ✔ |
| Manager | ✔ | ✔ | ✔ | ✔ | ✔ |
| Employee (Assignee) | ✔ | ✖ (own updates only) | ✖ | ✖ | ✖ |
| Viewer | ✔ | ✖ | ✖ | ✖ | ✖ |

Unauthorized access redirects to `/unauthorized`.

---

## 🔗 8) Integrations
| Module | Purpose |
|---|---|
| **Phases** | Phase progress auto-updated when tasks completed. |
| **Activity Log** | Logs every create/update/delete/status change. |
| **Members** | Task assignment limited to project members. |
| **Templates** | Predefined lists for quick setup. |
| **Notifications** | Task assignment and completion alerts. |

---

## 🔔 9) Notifications
| Event | Recipient | Message |
|---|---|---|
| Task Assigned | Assignee | “You’ve been assigned task [Title] in project [Project].” |
| Task Status Updated | Owner/Assignees | “Task [Title] marked [Status].” |
| Task Completed | Owner | “Task [Title] completed by [User].” |

---

## 📊 10) Calculations
- **Phase Progress** = avg(task.progress) where `phase_id` = current.
- **Project Progress** = avg(task.progress) across all active tasks.
- Recalculated dynamically on task update or daily sync.

---

## 📤 11) Reports
- Export CSV/PDF: Task title, phase, status, assignees, priority, due_date, progress.
- Filter options: status, phase, assignee, date range.

---

## 🧠 12) Future Enhancements
- Subtasks or checklist items.
- Task dependencies (blocked by/blocks).
- Time tracking and effort logs.
- Comments and attachments per task.
- Integration with Attendance for time spent.

---

### ✅ Summary
The **Project Tasks Section** empowers users to create, assign, and monitor actionable work within each project. With robust linking, real-time progress tracking, and integration across phases, templates, and notifications, it forms the execution backbone of the Projects Module in Karyalay ERP.

