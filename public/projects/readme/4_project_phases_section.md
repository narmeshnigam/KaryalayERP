**Karyalay ERP: Project Phases Section – Functional Specification**

---

### 🧩 Section: Project Phases
The **Phases Section** of the Projects Module allows breaking down a project into structured milestones or stages. Each phase helps monitor progress, set clear boundaries, and maintain better task organization.

Route: `/projects/view/:id#phases`

---

## 🎯 1) Goals
- Divide large projects into manageable milestones.
- Track completion and performance per phase.
- Aggregate phase-level task progress into overall project progress.
- Maintain flexible phase creation and ordering.

---

## ⚙️ 2) Structure & Components

### A) Phase List View
- Displays all phases under a project.
- Columns: Title, Duration (start–end), Status, % Progress, Assigned Owner, Task Count.
- Quick Actions: Edit, Delete, View Tasks, Add Task.
- Progress bar visualization.

### B) Add/Edit Phase Modal
Fields:
- Title (required)
- Description (optional)
- Start Date
- End Date
- Status (Pending/In Progress/Completed/On Hold)
- Sequence Order (drag to reorder)

Rules:
- Start ≤ End.
- Phase dates must fall within project date range (soft warning).
- Title unique per project.

### C) Phase Detail Drawer (optional UX)
- Displays quick summary of phase info.
- Links to view all tasks for that phase.

---

## 🧮 3) Database References

### Table: `project_phases`
(Already defined in DB Document)

Additional Notes:
- Each phase record belongs to one `project_id`.
- `progress` auto-calculated = avg(`project_tasks.progress`) where `phase_id` matches.
- When a phase is deleted, all linked tasks get `phase_id = NULL` (soft unlink, not delete).

---

## 🔗 4) Integrations
| Module | Usage |
|---------|--------|
| **Project Tasks** | Tasks link to `phase_id`; progress updates propagate to phase and project. |
| **Activity Log** | Each create/update/delete generates an activity record. |
| **Project Overview** | Snapshot of all phases shown in overview tab. |
| **Notebook** | Notes can be attached to specific phase if needed (linked_entity_type = 'Phase'). |

---

## 🚀 5) Backend Endpoints
| Method | Route | Purpose | Auth |
|---|---|---|---|
| GET | `/api/projects/:id/phases` | List all phases for a project | Yes |
| GET | `/api/projects/phase/:id` | Fetch details of a single phase | Yes |
| POST | `/api/projects/phase/add` | Create new phase | Owner/Admin |
| POST | `/api/projects/phase/update/:id` | Update phase info | Owner/Admin |
| DELETE | `/api/projects/phase/delete/:id` | Soft delete a phase | Owner/Admin |
| POST | `/api/projects/phase/reorder` | Update sequence ordering | Owner/Admin |

---

## 🔐 6) Permissions
| Role | View | Add/Edit | Delete | Reorder |
|---|---|---|---|---|
| Admin | ✔ | ✔ | ✔ | ✔ |
| Manager | ✔ | ✔ | ✔ | ✔ |
| Employee (Member) | ✔ | ✖ (unless Owner) | ✖ | ✖ |
| Viewer | ✔ | ✖ | ✖ | ✖ |

Unauthorized actions redirect to `/unauthorized`.

---

## 📊 7) Calculations
- **Phase % Progress** = average of all `task.progress` where `phase_id` = current.
- **Project % Progress** = average of all active phases.

Triggers or scheduled scripts can recompute progress daily or on relevant updates.

---

## 🔔 8) Notifications
| Event | Recipient | Message |
|---|---|---|
| Phase Created | Project Owner | “A new phase [Title] was added to [Project].” |
| Phase Status Changed | Members | “Phase [Title] marked as [Status].” |
| Phase Completed | Project Owner | “Phase [Title] completed successfully.” |

---

## 📤 9) Reports / Exports
- CSV/PDF export of phase list.
- Columns: Title, Duration, Status, Progress, Tasks, Owner.
- Filter: status/date range.

---

## 🧠 10) Future Enhancements
- Gantt visualization for all phases.
- Auto-close phases once all tasks completed.
- Dependencies (Phase B starts after A completes).
- Phase-wise burndown chart.

---

### ✅ Summary
The **Phases Section** allows modular structuring of projects into manageable milestones. It directly influences overall project progress, supports reordering and tracking, and provides seamless linkage with tasks, documents, and activity logs to maintain execution transparency.

