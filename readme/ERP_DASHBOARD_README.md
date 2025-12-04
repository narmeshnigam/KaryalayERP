# ERP Main Dashboard (Unified)

This dashboard aligns with the CRM Dashboard styling and provides a unified view of KPIs, charts, and activity widgets for Admins, Managers and Employees.

## Location
- File: `public/index.php`
- Route: `/dashboard` (same as `public/index.php` when served under `APP_URL/public`)

## Visual/UX
- Reused KPI card styles from `public/crm/dashboard.php` for visual consistency
- High-contrast gradients, larger values, pill-style deltas, and ring gauges
- Responsive grids: KPI (auto-fit), 2-col and 3-col chart sections

## Sections
1. Header Bar: greeting, current date/time, quick search, notifications, user role
2. KPI Summary Cards (role-aware):
   - CRM Total Leads (month)
   - CRM Conversion Rate (Admin/Manager)
   - Employees Total Active (Admin)
   - Present Today (Admin)
   - My Attendance Today (Employee)
   - Pending Tasks (+ Overdue)
   - Claims Pending (Admin/Manager)
   - Expenses Total (month) (Admin/Manager)
   - Payroll Completed % (Admin)
   - Visitors Today (Admin)
3. Visual Analytics:
   - Organizational Overview (Bar+Line combo)
   - Monthly Expense Trend (Line)
   - Attendance Heatmap (simple grid)
   - CRM Performance (Donut)
   - Task Completion Trend (Area/Line)
4. Activity Panels:
   - My Tasks (top 5)
   - Attendance summary (live or personal)
   - CRM Follow-ups (upcoming)
   - Reimbursements & Expenses (latest)
   - Salary Summary (current pay cycle)
   - Visitor Log (today)
5. Quick Actions: links to Leads, Calls, Expenses, Tasks, Attendance, Add Employee
6. Announcements list

## Data Wiring
The UI fetches JSON from the following endpoints when available and fails gracefully otherwise:
- `/public/api/dashboard/summary`
- `/public/api/dashboard/org-overview`
- `/public/api/dashboard/expense-trend`
- `/public/api/dashboard/crm-stats`
- `/public/api/dashboard/task-trend`
- `/public/api/dashboard/followups?limit=5`
- `/public/api/dashboard/finance-latest?limit=5`
- `/public/api/dashboard/visitors-today`
- `/public/api/announcements`
- `/public/api/crm/tasks?limit=5`

If an endpoint is missing or returns an error, the section shows placeholders without breaking the page.

## Role Awareness
- Admin: sees all global KPIs and panels
- Manager: similar to admin but scoped if backend enforces scoping
- Employee: sees personal attendance and tasks

## Notes
- Charts use Chart.js via CDN
- Attendance heatmap uses a lightweight grid (no extra library)
- Quick action links point to existing module entry pages

## Next Steps
- Implement the referenced API endpoints progressively
- Replace placeholder heatmap with matrix chart or calendar view
- Add server-side fallbacks for core KPIs if needed
- Optional: extract shared dashboard styles into a CSS file
