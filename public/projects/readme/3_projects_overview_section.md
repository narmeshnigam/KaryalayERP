**Karyalay ERP: Projects – Overview Section Specification**

---

### 🧩 Section: Project Overview (Workspace Home)
The **Project Overview Section** is the entry workspace for a single project. It consolidates top-level information, KPIs, quick actions, members, phases snapshot, recent activity, and key documents.

Route: `/projects/view/:id` → **Overview** tab (default)

---

## 🎯 1) Goals
- Present a clean, at-a-glance summary of project health and progress.
- Provide quick access to common actions (edit, add task/phase, invite member, upload doc).
- Surface risks (overdue tasks, soon-due milestones) and recent activity.

---

## 🔎 2) Data Shown
**Header Block**
- Title, project_code, type (Internal/Client), status, priority, owner
- Client chip (if applicable) with link to client profile
- Start/End dates, % progress, tags

**KPI Cards**
- Tasks: Total / Completed / Overdue
- Phases: Total / Completed (if phases used)
- Members: Active members count
- Timeline: Days elapsed vs total (start → end)

**Description & Notes**
- Short description (expandable), latest pinned note snippet

**Phases Snapshot** *(if phases exist)*
- List of phases with status chips, date range, progress bar

**Members Panel**
- Avatars with role (Owner/Contributor/Viewer), quick add/invite

**Recent Activity**
- Last 10 activities from `project_activity_log` (task updates, uploads, status changes)

**Key Documents**
- Recent 5 docs with type chip (NDA/Contract/Design/Report), quick upload

---

## ⚙️ 3) Actions & Quick Links
- **Edit Project** → `/projects/edit/:id`
- **Add Task** → opens modal (project_id prefilled)
- **Add Phase** → modal (sequence + dates)
- **Invite Member** → modal (search user, select role)
- **Upload Document** → modal (type + file)
- **Open Tasks Board** → `/projects/view/:id#tasks`
- **Open Documents** → `/projects/view/:id#documents`

---

## 🧱 4) UI Layout
- 2-column grid:
  - Left (2/3 width): Header, KPIs, Description, Phases Snapshot, Recent Activity
  - Right (1/3 width): Members Panel, Key Documents, Quick Actions card
- Responsive: stacks vertically on mobile

---

## 🧮 5) Derived Metrics
- **Project % Progress** = avg(phase progress) if phases exist, else avg(task progress)
- **Overdue Tasks** = count(tasks where status != Completed and due_date < today)
- **Timeline %** = elapsed_days / total_days (start → end)

---

## 🔗 6) API Endpoints (Read)
| Method | Route | Purpose |
|---|---|---|
| GET | `/api/projects/:id` | Core project detail (header data) |
| GET | `/api/projects/:id/kpis` | Tasks/Phases/Members/Timestamps KPIs |
| GET | `/api/projects/:id/phases` | List phases snapshot |
| GET | `/api/projects/:id/members` | Members with roles |
| GET | `/api/projects/:id/activity?limit=10` | Recent activity log |
| GET | `/api/projects/:id/documents?limit=5` | Recent docs list |

---

## 🚀 7) API Endpoints (Mutations)
| Method | Route | Purpose |
|---|---|---|
| POST | `/api/projects/update/:id` | Update title, dates, status, priority, tags, description |
| POST | `/api/projects/phase/add` | Create phase (title, dates, sequence) |
| POST | `/api/projects/member/add` | Add member (user_id, role) |
| DELETE | `/api/projects/member/remove/:member_id` | Remove member |
| POST | `/api/projects/document/upload` | Upload document (doc_type, file) |

All mutations push an entry to `project_activity_log`.

---

## ✅ 8) Validations & Rules
- `title` is mandatory; `project_code` unique and immutable after creation
- `owner_id` must be a valid user and a member (auto-add if not)
- `start_date` ≤ `end_date` (if both provided)
- Phase dates must lie within project dates (soft warning)
- Uploads: max 10MB; allowed types: pdf, docx, xlsx, png, jpg
- Status transitions: Draft → Active/On Hold; Active → On Hold/Completed; Completed → Archived

---

## 🔐 9) Permissions (Page-Level)
| Role | Overview View | Edit Project | Add Phase | Invite Member | Upload Docs |
|---|---|---|---|---|---|
| Admin | ✔ | ✔ | ✔ | ✔ | ✔ |
| Manager | ✔ | ✔ | ✔ | ✔ | ✔ |
| Employee (member) | ✔ | ✖ (unless Owner) | ✖ | ✖ | ✔ |
| Viewer (member) | ✔ | ✖ | ✖ | ✖ | ✖ |

Unauthorized actions redirect to `/unauthorized`.

---

## 🔔 10) Notifications
- Member added → Email/WhatsApp to user: “You’ve been added to project [Title].”
- Project status changed → Notify all members
- Document uploaded → Notify Owner & upload author

---

## 📤 11) Export/Print
- Export overview snapshot (PDF): header, KPIs, phases table, members list, top docs, activity summary

---

## 🧠 12) Future Enhancements
- Project health score (scope drift, overdue ratio, member load)
- Smart alerts ("Milestone at risk" based on timeline % vs progress %)
- Pin a Notebook note to Overview header

---

### ✅ Summary
The **Project Overview Section** provides a concise, actionable summary of a project with KPIs, snapshots, and quick actions. It is the default landing tab in the project workspace and the hub for day‑to‑day monitoring and coordination.

