**Karyalay ERP: Clients ↔ Projects Integration Map**

---

### 🧩 1. Overview
The **Clients ↔ Projects Integration Map** defines how leads, once converted from the CRM, evolve into clients and connect with one or more projects. It establishes relational continuity between **CRM**, **Clients**, and **Projects**, ensuring seamless data flow and consistent ownership tracking.

This document highlights entity relationships, workflow automation, permissions, and synchronization rules.

---

## 🔗 2. Entity Relationships
| Entity | Relation | Description |
|---------|-----------|--------------|
| **CRM Leads (`crm_leads`)** | → **Clients (`clients`)** | When a lead is converted, a new client record is created. Key details (name, contact info, interest) are transferred. |
| **Clients (`clients`)** | → **Projects (`projects`)** | Each client can have multiple projects. Projects inherit client details like contact info and owner reference. |
| **Projects (`projects`)** | ↔ **Project Members / Tasks / Phases / Documents** | Projects are internally managed workspaces linked to clients. All project operations trace back to the client. |
| **Clients (`clients`)** | ↔ **Contacts (`contacts`)** | Clients store multiple contacts (decision makers, managers) linked for project coordination. |

---

## 🧮 3. Database Relationships
| Table | Key Fields | Relationship |
|--------|-------------|---------------|
| `crm_leads` | id | When converted, generates a new entry in `clients`. |
| `clients` | id, lead_id | `lead_id` references `crm_leads.id` for traceability. |
| `projects` | client_id | Foreign key referencing `clients.id`. Optional (nullable) for internal projects. |
| `contacts` | client_id | Each contact belongs to a client record. |
| `project_members`, `project_tasks`, `project_documents` | project_id | Indirectly linked to clients through projects. |

**Cascade behavior:**
- Client deletion → sets `client_id = NULL` in related projects (archives them). No hard delete.
- Project deletion → does not affect client record.

---

## 🔁 4. Workflow Summary
### Step-by-Step Flow:
1. **Lead Created in CRM** → contains contact info, source, and product/service interest.
2. **Lead Conversion** → creates a new record in `clients`.
3. **Client Profile Generated** → stores company details, addresses, and linked contacts.
4. **Project Creation (optional)** → directly from client profile screen.
5. **Project Execution** → linked to `client_id`, inherits basic details (name, contact, owner).
6. **Activity Sync** → project-related activities reflected under client timeline for overview.

**Internal Projects:**
- When `client_id` = NULL → project categorized as *Internal*. These projects still follow the same structure but are not associated with external clients.

---

## 🧭 5. UI & User Flow
**Conversion & Linking Interface:**
1. **From Lead → Convert to Client**
   - Option to create a project immediately post-conversion.
   - Pre-fills project form fields with client/lead info.

2. **From Client Profile → Create Project**
   - Auto-links new project with `client_id`.
   - Displays all existing projects under that client.

**Navigation Path:**
`CRM → Leads → Convert → Clients → View Client → Create Project → Projects Module`

---

## 🔄 6. Data Synchronization Rules
| Trigger | Action |
|----------|--------|
| Client name updated | Project header auto-updates (display only). |
| Client deleted | Projects archived (status = Archived, client_id = NULL). |
| Contact info updated | Display fields in project overview update dynamically. |
| Client owner changed | Ownership reflected in all linked projects (soft sync). |

Data synchronization runs both **event-based (real-time)** and **scheduled (nightly batch)** to maintain consistency.

---

## 🔐 7. Permissions
| Role | CRM Leads | Clients | Projects |
|------|------------|----------|-----------|
| **Admin** | Full access | Full access | Full access |
| **CRM Executive** | Create, Convert, View | View linked clients/projects | Read-only project access |
| **Project Manager** | Read-only | View client info | Manage linked projects |
| **Employee** | ✖ | ✖ | Assigned project access only |

Unauthorized access redirects to `/unauthorized`.

---

## 🔔 8. Notifications
| Event | Recipient | Type | Message |
|--------|------------|------|----------|
| Lead Converted | CRM Executive | Email/WhatsApp | “Lead [Name] successfully converted to Client [Company].” |
| Project Created | Project Owner, CRM Owner | Email | “New project [Title] created under client [Client Name].” |
| Client Info Updated | Project Owners | Email | “Client [Name] information updated; synced with linked projects.” |

---

## 🔗 9. Data Flow Diagram (Textual Representation)
```
[CRM Lead]
   ↓ Conversion
[Client Created] ← retains → [Lead ID]
   ↓  (Optional Project Creation)
[Project Created] ← inherits → [Client Info, Owner, Contact]
   ↓  (Execution Stage)
[Tasks / Members / Documents / Phases / Logs]
   ↓
[Project Activity Summary synced back to Client Overview]
```

---

## 🧠 10. Future Enhancements
- **Client Analytics Dashboard:** Summary of all projects, revenue, and completion rates.
- **Cross-Module Reporting:** Combine CRM + Clients + Projects for lifecycle performance.
- **Client Portal:** Allow external client login to view project progress and share documents securely.
- **Automated Conversion Rules:** AI-based client conversion trigger from CRM based on interaction scoring.

---

### ✅ Summary
The **Clients ↔ Projects Integration** ensures seamless transition from sales to execution. Leads evolve into clients, and clients link to structured projects with shared data flow, activity tracking, and synchronized ownership — enabling consistent visibility from CRM to delivery within Karyalay ERP.

