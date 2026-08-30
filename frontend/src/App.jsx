import { lazy, Suspense, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { BarChart3, Bell, CalendarPlus, CheckCircle2, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, CircleDollarSign, Eye, FileText, FolderKanban, Globe2, Landmark, LayoutDashboard, LoaderCircle, LogOut, Menu, MinusCircle, Pencil, Plus, ReceiptText, RefreshCw, RotateCcw, Settings as SettingsIcon, ShoppingCart, Trash2, Users as UsersIcon, WalletCards, X } from 'lucide-react'
import './App.css'
import { API_BASE_URL } from './api/apiClient'

const API_BASE = API_BASE_URL

const projectStatuses = ['New', 'In Progress', 'Waiting Payment', 'Delivered', 'Completed', 'Cancelled', 'On Hold']
const paymentStatuses = ['Unpaid', 'Partially Paid', 'Fully Paid', 'Overdue']
const paymentMethods = ['Cash', 'KPay', 'WavePay', 'Bank Transfer', 'AYA Pay', 'CB Pay', 'Other']
const feeTypes = ['Server', 'VPS', 'SSL', 'Maintenance', 'API Subscription', 'Other']
const billingCycles = ['Monthly', 'Quarterly', 'Half Yearly', 'Yearly', 'One Time']
const expenseCategories = ['Domain Purchase', 'Hosting Purchase', 'VPS Purchase', 'Server Cost', 'SSL Cost', 'API Cost', 'SMS Cost', 'Developer Cost', 'Design Cost', 'Transport', 'Other']
const invoiceTypes = ['Project Invoice', 'Upfront Invoice', 'Progress Invoice', 'Final Invoice', 'Hosting Invoice', 'Domain Invoice', 'Maintenance Invoice', 'Other']
const invoiceBlockNames = { header: 'Header', meta: 'Customer & Invoice', callout: 'Payment Callout', table: 'Items Table', summary: 'Totals', notes: 'Notes', footer: 'Payment & Contact' }
const invoiceFontFamilies = ['Inter, Segoe UI, Arial, sans-serif', 'Arial, Helvetica, sans-serif', 'Georgia, Times New Roman, serif', 'Courier New, monospace']
const defaultInvoiceDesign = {
  page: { fontFamily: invoiceFontFamilies[0], fontSize: 11, lineHeight: 1.45, color: '#101827', background: '#ffffff', accent: '#de7600' },
  table: { headerBackground: '#101827', headerColor: '#ffffff', borderColor: '#dce3ec', borderWidth: 1, cellPadding: 10, fontSize: 10, striped: false, columnWidths: '10,61,9,20' },
  blocks: {
    header: { visible: true, x: 6.7, y: 5, width: 86.6, minHeight: 0, padding: 0, margin: 0, textAlign: 'left', fontFamily: invoiceFontFamilies[0], fontSize: 14, fontWeight: 400, lineHeight: 1.35, color: '#101827', background: 'transparent' },
    meta: { visible: true, x: 6.7, y: 19, width: 86.6, minHeight: 0, padding: 0, margin: 0, textAlign: 'left', fontFamily: invoiceFontFamilies[0], fontSize: 11, fontWeight: 400, lineHeight: 1.5, color: '#53627a', background: 'transparent' },
    callout: { visible: true, x: 6.7, y: 31, width: 86.6, minHeight: 0, padding: 18, margin: 0, textAlign: 'left', fontFamily: invoiceFontFamilies[0], fontSize: 12, fontWeight: 400, lineHeight: 1.4, color: '#8d470b', background: '#fff3e7' },
    table: { visible: true, x: 6.7, y: 46, width: 86.6, minHeight: 0, padding: 0, margin: 0, textAlign: 'left', fontFamily: invoiceFontFamilies[0], fontSize: 10, fontWeight: 400, lineHeight: 1.35, color: '#101827', background: 'transparent' },
    summary: { visible: true, x: 49, y: 67, width: 44.3, minHeight: 0, padding: 0, margin: 0, textAlign: 'right', fontFamily: invoiceFontFamilies[0], fontSize: 11, fontWeight: 400, lineHeight: 1.4, color: '#53627a', background: 'transparent' },
    notes: { visible: true, x: 6.7, y: 83, width: 86.6, minHeight: 0, padding: 0, margin: 0, textAlign: 'left', fontFamily: invoiceFontFamilies[0], fontSize: 10, fontWeight: 400, lineHeight: 1.45, color: '#53627a', background: 'transparent' },
    footer: { visible: true, x: 6.7, y: 89, width: 86.6, minHeight: 0, padding: 0, margin: 0, textAlign: 'left', fontFamily: invoiceFontFamilies[0], fontSize: 10, fontWeight: 400, lineHeight: 1.55, color: '#53627a', background: 'transparent' },
  },
}

function invoiceDesignFrom(value) {
  try {
    const parsed = typeof value === 'string' && value ? JSON.parse(value) : value || {}
    return {
      page: { ...defaultInvoiceDesign.page, ...(parsed.page || {}) },
      table: { ...defaultInvoiceDesign.table, ...(parsed.table || {}) },
      blocks: Object.fromEntries(Object.keys(defaultInvoiceDesign.blocks).map((key) => [key, { ...defaultInvoiceDesign.blocks[key], ...(parsed.blocks?.[key] || {}) }])),
    }
  } catch { return structuredClone(defaultInvoiceDesign) }
}
const DashboardCharts = lazy(() => import('./components/Charts.jsx').then((module) => ({ default: module.DashboardCharts })))
const MonthlyReportChart = lazy(() => import('./components/Charts.jsx').then((module) => ({ default: module.MonthlyReportChart })))

function currency(value) {
  return `${Number(value || 0).toLocaleString()} MMK`
}

function today() {
  const date = new Date()
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
}

function addYearClamped(value) {
  const [year, month, day] = String(value).split('-').map(Number)
  if (!year || !month || !day) return ''
  const targetYear = year + 1
  const lastDay = new Date(Date.UTC(targetYear, month, 0)).getUTCDate()
  return `${targetYear}-${String(month).padStart(2, '0')}-${String(Math.min(day, lastDay)).padStart(2, '0')}`
}

function periodDates(period) {
  const now = new Date(`${today()}T00:00:00`)
  const format = (date) => new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
  if (period === 'today') return { date_from: format(now), date_to: format(now) }
  if (period === 'week') {
    const monday = new Date(now); monday.setDate(now.getDate() - ((now.getDay() + 6) % 7))
    const sunday = new Date(monday); sunday.setDate(monday.getDate() + 6)
    return { date_from: format(monday), date_to: format(sunday) }
  }
  if (period === 'month') return { date_from: format(new Date(now.getFullYear(), now.getMonth(), 1)), date_to: format(new Date(now.getFullYear(), now.getMonth() + 1, 0)) }
  return { date_from: '', date_to: '' }
}

function useDebouncedValue(value, delay = 300) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(timer)
  }, [value, delay])
  return debounced
}

function makeProjectCode() {
  return `PRJ-${Date.now().toString(36).toUpperCase()}-${crypto.randomUUID().slice(0, 4).toUpperCase()}`
}

function App() {
  const [token, setToken] = useState(localStorage.getItem('token') || '')
  const [user, setUser] = useState(JSON.parse(localStorage.getItem('user') || 'null'))
  const [active, setActive] = useState('Dashboard')
  const [menuOpen, setMenuOpen] = useState(false)

  const api = useMemo(() => ({
    async request(path, options = {}) {
      const response = await fetch(`${API_BASE}${path}`, {
        ...options,
        headers: {
          'Content-Type': 'application/json',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          ...(options.headers || {}),
        },
      })
      const payload = await response.json().catch(() => ({ success: false, message: 'Invalid server response' }))
      if (!response.ok || !payload.success) {
        if (response.status === 401) {
          setToken('')
          setUser(null)
          localStorage.removeItem('token')
          localStorage.removeItem('user')
        }
        const detail = payload.errors ? Object.values(payload.errors).join(' ') : ''
        throw new Error(`${payload.message || 'Request failed'} ${detail}`.trim())
      }
      return payload.data
    },
  }), [token])

  function handleLogin(auth) {
    setToken(auth.token)
    setUser(auth.user)
    localStorage.setItem('token', auth.token)
    localStorage.setItem('user', JSON.stringify(auth.user))
  }

  async function logout() {
    try { await api.request('/auth/logout', { method: 'POST' }) } catch { /* Clear local session even if the API is unavailable. */ }
    setToken('')
    setUser(null)
    localStorage.clear()
  }

  if (!token) {
    return <LoginPage onLogin={handleLogin} />
  }

  const nav = ['Dashboard', 'Projects', 'Payments', 'Domain Billing', 'User Financial', 'Recurring Fees', 'Expenses', 'Invoices', 'Receipts', 'Reminders', 'Reports', ...(user?.role === 'Admin' ? ['Users', 'Settings'] : [])]

  return (
    <div className="app-shell">
      <aside className={`sidebar ${menuOpen ? 'open' : ''}`}>
        <div className="brand">KSSPM</div>
        {nav.map((item) => (
          <button key={item} className={active === item ? 'active' : ''} onClick={() => { setActive(item); setMenuOpen(false) }}>
            <span>{iconFor(item)}</span>{item}
          </button>
        ))}
      </aside>
      {menuOpen && <button className="sidebar-scrim mobile-only" aria-label="Close navigation" onClick={() => setMenuOpen(false)} />}
      <main className="main">
        <header className="topbar">
          <button className="icon-btn mobile-only" onClick={() => setMenuOpen(!menuOpen)} aria-label="Menu"><Menu size={20} /></button>
          <div>
            <h1>{active}</h1>
            <p>Project-centered financial management</p>
          </div>
          <div className="userbox">
            <span>{user?.name}</span>
            <small>{user?.role}</small>
            <ActionButton label="Log out" icon={LogOut} onClick={logout} />
          </div>
        </header>
        {active === 'Dashboard' && <Dashboard api={api} />}
        {active === 'Projects' && <Projects api={api} canWrite={user?.role !== 'Viewer'} />}
        {active === 'Payments' && <GenericModule api={api} title="Payments" endpoint="/payments" fields={paymentFields} columns={paymentColumns} options={{ payment_type: ['Upfront','Progress Payment','Final Payment','Other'] }} filters={['search','project_id','financial_account_id','date_from','date_to']} canWrite={user?.role !== 'Viewer'} />}
        {active === 'Domain Billing' && <DomainBilling api={api} canWrite={user?.role !== 'Viewer'} />}
        {active === 'User Financial' && <UserFinancial api={api} canWrite={user?.role !== 'Viewer'} canManage={user?.role === 'Admin'} />}
        {active === 'Recurring Fees' && <GenericModule api={api} title="Recurring Fees" endpoint="/recurring-fees" fields={feeFields} columns={feeColumns} options={{ fee_type: feeTypes, source_type: ['Manual','Domain Billing'], billing_cycle: billingCycles, status: ['Not Due', 'Due Soon', 'Due Today', 'Overdue', 'Paid', 'Cancelled'] }} filters={['search','fee_type','source_type','status','date_from','date_to']} canWrite={user?.role !== 'Viewer'} markPaid />}
        {active === 'Expenses' && <GenericModule api={api} title="Expenses" endpoint="/expenses" fields={expenseFields} columns={expenseColumns} options={{ expense_category: expenseCategories, payment_method: paymentMethods }} filters={['search','project_id','expense_category','payment_method','date_from','date_to']} canWrite={user?.role !== 'Viewer'} />}
        {active === 'Invoices' && <Invoices api={api} canWrite={user?.role !== 'Viewer'} />}
        {active === 'Receipts' && <GenericModule api={api} title="Receipts" endpoint="/receipts" fields={receiptFields} columns={receiptColumns} options={{ payment_method: paymentMethods }} filters={['search','project_id','date_from','date_to']} canWrite={user?.role !== 'Viewer'} />}
        {active === 'Reminders' && <Reminders api={api} onNavigate={setActive} canWrite={user?.role !== 'Viewer'} />}
        {active === 'Reports' && <Reports api={api} />}
        {active === 'Users' && <Users api={api} canManage={user?.role === 'Admin'} />}
        {active === 'Settings' && <Settings api={api} canManage={user?.role === 'Admin'} />}
      </main>
    </div>
  )
}

function iconFor(item) {
  const Icon = ({ Dashboard: LayoutDashboard, Projects: FolderKanban, Payments: WalletCards, 'Domain Billing': Globe2, 'User Financial': Landmark, 'Recurring Fees': RefreshCw, Expenses: MinusCircle, Invoices: FileText, Receipts: ReceiptText, Reminders: Bell, Reports: BarChart3, Users: UsersIcon, Settings: SettingsIcon })[item] || CheckCircle2
  return <Icon size={18} strokeWidth={1.8} />
}

function LoginPage({ onLogin }) {
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')

  async function submit(e) {
    e.preventDefault()
    setError('')
    try {
      const response = await fetch(`${API_BASE}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      })
      const payload = await response.json()
      if (!payload.success) throw new Error(payload.message)
      onLogin(payload.data)
    } catch (err) {
      setError(err.message)
    }
  }

  return (
    <div className="login-wrap">
      <form className="login-card" onSubmit={submit}>
        <h1>KSSPM</h1>
        <p>Project and Financial Management</p>
        {error && <div className="alert">{error}</div>}
        <label>Email<input required type="email" autoComplete="username" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
        <label>Password<input required type="password" autoComplete="current-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></label>
        <button className="primary">Login</button>
      </form>
    </div>
  )
}

function Dashboard({ api }) {
  const [summary, setSummary] = useState({})
  const [activity, setActivity] = useState({})
  const [charts, setCharts] = useState({ monthly_income: [], monthly_expenses: [], payment_statuses: {}, recurring_due: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    Promise.all([api.request('/dashboard/summary'), api.request('/dashboard/recent-activity'), api.request('/dashboard/charts')])
      .then(([summaryData, activityData, chartData]) => { setSummary(summaryData); setActivity(activityData); setCharts(chartData) })
      .catch((err) => setError(err.message)).finally(() => setLoading(false))
  }, [api])

  const cards = [
    ['Total Projects', summary.total_projects],
    ['Active Projects', summary.active_projects],
    ['Completed', summary.completed_projects],
    ['Contract Value', currency(summary.total_contract_value)],
    ['Received', currency(summary.total_received)],
    ['Outstanding', currency(summary.total_outstanding)],
    ['Month Income', currency(summary.this_month_income)],
    ['Month Expenses', currency(summary.this_month_expenses)],
    ['Net Profit', currency(summary.net_profit)],
    ['Overdue Payments', summary.overdue_payments],
    ['Fees Due This Month', summary.server_fees_due_this_month],
    ['Upcoming Renewals', summary.upcoming_renewals],
  ]

  const monthMap = new Map()
  charts.monthly_income.forEach((row) => monthMap.set(row.month, { month: row.month, income: Number(row.total), expenses: 0 }))
  charts.monthly_expenses.forEach((row) => monthMap.set(row.month, { ...(monthMap.get(row.month) || { month: row.month, income: 0 }), expenses: Number(row.total) }))
  const monthly = [...monthMap.values()].sort((a, b) => a.month.localeCompare(b.month))
  const paymentPie = Object.entries(charts.payment_statuses || {}).map(([name, value]) => ({ name, value }))

  if (loading) return <section className="page"><Loading label="Loading dashboard" /></section>
  return (
    <section className="page">
      {error && <div className="alert">{error}</div>}
      <div className="summary-grid">{cards.map(([label, value]) => <Card key={label} label={label} value={value ?? 0} />)}</div>
      <Suspense fallback={<Loading label="Loading charts" />}><DashboardCharts monthly={monthly} paymentPie={paymentPie} recurringDue={charts.recurring_due} formatCurrency={currency} /></Suspense>
      <div className="two-col">
        <Panel title="Recent Payments"><MiniList rows={activity.recent_payments || []} main="project_name" sub="financial_account_name" amount="amount" /></Panel>
        <Panel title="Upcoming Renewals"><MiniList rows={activity.upcoming_renewals || []} main="fee_name" sub="project_name" amount="amount" date="next_due_date" /></Panel>
        <Panel title="Overdue Balances"><MiniList rows={activity.overdue_balances || []} main="project_name" sub="customer_company_name" amount="remaining_balance" /></Panel>
        <Panel title="Recent Projects"><MiniList rows={activity.recent_projects || []} main="project_name" sub="customer_company_name" /></Panel>
      </div>
    </section>
  )
}

function Projects({ api, canWrite }) {
  const emptyProject = { project_code: makeProjectCode(), project_name: '', project_type: '', status: 'New', priority: 'Medium', start_date: today(), delivery_date: '', completion_date: '', customer_company_name: '', contact_person: '', contact_phone: '', contact_email: '', customer_address: '', contract_amount: 0, upfront_required_amount: 0, discount_amount: 0, tax_amount: 0, payment_due_date: '', currency: 'MMK', server_ip: '', git_repository_url: '', admin_panel_url: '', production_url: '', description: '', technical_notes: '', notes: '', initial_server_billing_enabled: 0, initial_server_domain_name: '', initial_server_period_label: 'First Year', initial_server_quote_date: today(), initial_server_customer_price: '', initial_server_customer_due_date: '', initial_server_reminder_days: 30, initial_server_notes: '' }
  const [rows, setRows] = useState([])
  const [editing, setEditing] = useState(null)
  const [detail, setDetail] = useState(null)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [paymentStatus, setPaymentStatus] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({ page: 1, pages: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const debouncedSearch = useDebouncedValue(search)

  const load = useCallback(() => {
    setLoading(true); setError('')
    const query = new URLSearchParams({ search: debouncedSearch, status, payment_status: paymentStatus, date_from: dateFrom, date_to: dateTo, page: String(page), limit: '20' })
    return api.request(`/projects?${query}`).then((data) => { setRows(data.rows || data); setPagination(data.pagination || { page: 1, pages: 1, total: data.length }) }).catch((err) => setError(err.message)).finally(() => setLoading(false))
  }, [api, debouncedSearch, status, paymentStatus, dateFrom, dateTo, page])
  useEffect(() => { load() }, [load])

  async function save(data) {
    if (saving) return
    setSaving(true)
    try {
      await api.request(`/projects${data.id ? `/${data.id}` : ''}`, { method: data.id ? 'PUT' : 'POST', body: JSON.stringify(data) })
      setEditing(null); await load()
    } catch (err) { setError(err.message) } finally { setSaving(false) }
  }

  async function remove(id) {
    if (!confirm('Delete this project?')) return
    try { await api.request(`/projects/${id}`, { method: 'DELETE' }); await load() } catch (err) { setError(err.message) }
  }

  async function show(id) {
    try { setDetail(await api.request(`/projects/${id}`)) } catch (err) { setError(err.message) }
  }

  async function editProject(id) {
    try { setError(''); setEditing(await api.request(`/projects/${id}`)) } catch (err) { setError(err.message) }
  }

  return (
    <section className="page">
      <Toolbar title="Projects" canWrite={canWrite} onAdd={() => { setError(''); setEditing(emptyProject) }} />
      <div className="filters">
        <input placeholder="Search project or customer" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} />
        <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}><option value="">All statuses</option>{projectStatuses.map((x) => <option key={x}>{x}</option>)}</select>
        <select value={paymentStatus} onChange={(e) => { setPaymentStatus(e.target.value); setPage(1) }}><option value="">All payment</option>{paymentStatuses.map((x) => <option key={x}>{x}</option>)}</select>
        <input aria-label="Created from" type="date" value={dateFrom} onChange={(e) => { setDateFrom(e.target.value); setPage(1) }} />
        <input aria-label="Created to" type="date" value={dateTo} onChange={(e) => { setDateTo(e.target.value); setPage(1) }} />
        {(search || status || paymentStatus || dateFrom || dateTo) && <button onClick={() => { setSearch(''); setStatus(''); setPaymentStatus(''); setDateFrom(''); setDateTo(''); setPage(1) }}>Clear</button>}
      </div>
      {error && <div className="alert">{error}</div>}
      {loading ? <Loading label="Loading projects" /> : <Table rows={rows} columns={projectColumns} actions={(row) => (
        <>
          <ActionButton label="View project" icon={Eye} onClick={() => show(row.id)} />
          {canWrite && <ActionButton label="Edit project" icon={Pencil} onClick={() => editProject(row.id)} />}
          {canWrite && <ActionButton label="Delete project" icon={Trash2} danger onClick={() => remove(row.id)} />}
        </>
      )} />}
      <Pagination value={pagination} onChange={setPage} />
      {editing && <Modal title={editing.id ? 'Edit Project' : 'Create Project'} onClose={() => setEditing(null)} wide>{error && <div className="modal-alert alert">{error}</div>}<ProjectForm initial={editing} onSubmit={save} submitting={saving} /></Modal>}
      {detail && <Modal title={detail.project_name} onClose={() => setDetail(null)} wide><ProjectDetail api={api} project={detail} /></Modal>}
    </section>
  )
}

function ProjectForm({ initial, onSubmit, submitting }) {
  const [form, setForm] = useState(initial)
  const set = (key, value) => setForm({ ...form, [key]: value })
  const isCreate = !initial.id
  const fields = [
    ['Project', ['project_code','project_name','project_type','status','priority','start_date','delivery_date','completion_date','description']],
    ['Project Owner / Customer', ['customer_company_name','contact_person','contact_phone','contact_email','customer_address']],
    ['Financial', ['contract_amount','upfront_required_amount','discount_amount','tax_amount','payment_due_date','currency','notes']],
  ]
  const technicalFields = ['server_ip','git_repository_url','admin_panel_url','production_url','technical_notes']
  const options = { status: projectStatuses, priority: ['Low','Medium','High','Urgent'] }
  return (
    <form className="form-grid" onSubmit={(e) => { e.preventDefault(); onSubmit(form) }}>
      {fields.map(([title, names]) => (
        <fieldset key={title}>
          <legend>{title}</legend>
          {names.map((name) => fieldControl(name, form[name], (value) => set(name, value), options[name] || null, null, ['project_code','project_name','status','contract_amount'].includes(name)))}
        </fieldset>
      ))}
      {isCreate && <fieldset className="full"><legend>Initial Server Billing (Domain + Hosting)</legend><label className="check-row"><input type="checkbox" checked={Boolean(form.initial_server_billing_enabled)} onChange={(event) => set('initial_server_billing_enabled', event.target.checked ? 1 : 0)} />Add initial server billing</label>{Boolean(form.initial_server_billing_enabled) && <><label>Domain Name<input value={form.initial_server_domain_name || ''} onChange={(event) => set('initial_server_domain_name', event.target.value)} /></label><label>Period Label<input required maxLength="50" value={form.initial_server_period_label || ''} onChange={(event) => set('initial_server_period_label', event.target.value)} /></label><label>Quote Date<input required type="date" value={form.initial_server_quote_date || ''} onChange={(event) => set('initial_server_quote_date', event.target.value)} /></label><label>Customer Server Price<input required type="number" min="0" step="0.01" value={form.initial_server_customer_price ?? ''} onChange={(event) => set('initial_server_customer_price', event.target.value)} /></label><label>Customer Due Date<input type="date" min={form.initial_server_quote_date || undefined} value={form.initial_server_customer_due_date || ''} onChange={(event) => set('initial_server_customer_due_date', event.target.value)} /></label><label>Reminder Days<input required type="number" min="0" max="365" step="1" value={form.initial_server_reminder_days ?? 30} onChange={(event) => set('initial_server_reminder_days', event.target.value)} /></label><label className="full">Server Billing Notes<textarea value={form.initial_server_notes || ''} onChange={(event) => set('initial_server_notes', event.target.value)} /></label></>}</fieldset>}
      <details className="optional-fields full"><summary>Technical details</summary><fieldset>{technicalFields.map((name) => fieldControl(name, form[name], (value) => set(name, value)))}</fieldset></details>
      <div className="form-actions"><button className="primary" disabled={submitting}>{submitting ? 'Saving…' : 'Save Project'}</button></div>
    </form>
  )
}

function ProjectDetail({ api, project }) {
  const [tab, setTab] = useState('Overview')
  const tabs = ['Overview', 'Domain Billing', 'Payments', 'Recurring Fees', 'Expenses', 'Invoices', 'Receipts', 'Notes']
  const listEndpoints = { 'Domain Billing': 'domain-billings', Payments: 'payments', 'Recurring Fees': 'recurring-fees', Expenses: 'expenses', Invoices: 'invoices', Receipts: 'receipts' }
  const countKeys = { 'Domain Billing': 'domain_billings', Payments: 'payments', 'Recurring Fees': 'recurring_fees', Expenses: 'expenses', Invoices: 'invoices', Receipts: 'receipts' }
  const overviewSections = [
    ['Project', ['project_code','project_name','project_type','status','priority','start_date','delivery_date','completion_date','description']],
    ['Project Owner / Customer', ['customer_company_name','contact_person','contact_phone','contact_email','customer_address']],
    ['Domain & Server', ['domain_name','domain_purchase_date','domain_reminder_date','domain_payment_date','domain_server_price','hosting_provider','server_provider']],
    ['Technical', ['server_ip','git_repository_url','admin_panel_url','production_url','technical_notes']],
  ]
  return (
    <>
      <div className="summary-grid compact">
        <Card label="Contract" value={currency(project.summary.total_payable)} />
        <Card label="Paid" value={currency(project.summary.total_paid)} />
        <Card label="Balance" value={currency(project.summary.remaining_balance)} />
        <Card label="Expense" value={currency(project.summary.total_expenses)} />
        <Card label="Profit" value={currency(project.summary.profit)} />
      </div>
      <div className="financial-strip"><span>Discount <strong>{currency(project.summary.discount_amount)}</strong></span><span>Tax <strong>{currency(project.summary.tax_amount)}</strong></span><span>Payment Progress <strong>{project.summary.payment_percentage}%</strong></span><span>Status <strong className={`badge ${String(project.summary.payment_status).toLowerCase().replaceAll(' ', '-')}`}>{project.summary.payment_status}</strong></span></div>
      <div className="tabs">{tabs.map((x) => <button key={x} className={tab === x ? 'active' : ''} onClick={() => setTab(x)}>{x}{countKeys[x] && <span>{project.record_counts?.[countKeys[x]] || 0}</span>}</button>)}</div>
      {tab === 'Overview' && <div className="overview-sections">{overviewSections.map(([title, keys]) => <DetailSection key={title} title={title} keys={keys} record={project} />)}</div>}
      {tab === 'Notes' && <div className="print-box"><p>{project.notes || 'No notes yet.'}</p><p>{project.technical_notes}</p></div>}
      {listEndpoints[tab] && <ProjectHistoryTable key={tab} api={api} projectId={project.id} endpoint={listEndpoints[tab]} />}
    </>
  )
}

function ProjectHistoryTable({ api, projectId, endpoint }) {
  const [rows, setRows] = useState([])
  const [pagination, setPagination] = useState({ page: 1, pages: 1, total: 0 })
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  useEffect(() => {
    setLoading(true); setError('')
    api.request(`/projects/${projectId}/${endpoint}?page=${page}&limit=10`)
      .then((data) => { setRows(data.rows || data); setPagination(data.pagination || { page: 1, pages: 1, total: data.length }) })
      .catch((err) => setError(err.message)).finally(() => setLoading(false))
  }, [api, projectId, endpoint, page])
  if (error) return <div className="alert">{error}</div>
  if (loading) return <Loading label="Loading project history" />
  const columns = Object.keys(rows[0] || {}).filter((key) => !['created_at','updated_at'].includes(key)).slice(0, 8).map((key) => ({ key, label: label(key) }))
  return <><Table rows={rows} columns={columns} /><Pagination value={pagination} onChange={setPage} /></>
}

function DetailSection({ title, keys, record }) {
  return <section className="detail-section"><h3>{title}</h3><div className="detail-grid">{keys.filter((key) => record[key] !== null && record[key] !== '').map((key) => <p key={key}><strong>{label(key)}</strong><span>{key.includes('amount') || key.includes('price') ? currency(record[key]) : String(record[key] ?? '-')}</span></p>)}</div></section>
}

function GenericModule({ api, title, endpoint, fields, columns, options = {}, filters = [], canWrite, markPaid }) {
  const [rows, setRows] = useState([])
  const [projects, setProjects] = useState([])
  const [financialAccounts, setFinancialAccounts] = useState([])
  const [editing, setEditing] = useState(null)
  const [detail, setDetail] = useState(null)
  const [paying, setPaying] = useState(null)
  const [filterValues, setFilterValues] = useState(Object.fromEntries(filters.map((name) => [name, ''])))
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({ page: 1, pages: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const debouncedSearch = useDebouncedValue(filterValues.search || '')
  const listQuery = new URLSearchParams({ ...filterValues, search: debouncedSearch, page: String(page), limit: '25' }).toString()
  const needsFinancialAccounts = fields.some((field) => field.name === 'financial_account_id') || markPaid

  const empty = Object.fromEntries(fields.map((field) => [field.name, field.default ?? '']))
  const load = useCallback(() => {
    setLoading(true); setError('')
    return api.request(`${endpoint}?${listQuery}`).then((data) => { setRows(data.rows || data); setPagination(data.pagination || { page: 1, pages: 1, total: data.length }) }).catch((err) => setError(err.message)).finally(() => setLoading(false))
  }, [api, endpoint, listQuery])
  useEffect(() => { load() }, [load])
  useEffect(() => {
    if (endpoint === '/users') return
    api.request('/projects?compact=1').then(setProjects).catch((err) => setError(err.message))
    if (needsFinancialAccounts) api.request('/financial-accounts').then(setFinancialAccounts).catch((err) => setError(err.message))
  }, [api, endpoint, needsFinancialAccounts])

  async function add() {
    setError('')
    setEditing(empty)
  }

  async function save(data) {
    if (saving) return
    setSaving(true)
    try {
      await api.request(`${endpoint}${data.id ? `/${data.id}` : ''}`, { method: data.id ? 'PUT' : 'POST', body: JSON.stringify(data) })
      setEditing(null); await load()
    } catch (err) { setError(err.message) } finally { setSaving(false) }
  }
  async function view(row) {
    try {
      if (row.source_type === 'Domain Billing') setDetail({ kind: 'domain', record: await api.request(`/domain-billings/${row.source_id}`) })
      else setDetail({ kind: 'record', record: await api.request(`${endpoint}/${row.id}`) })
    } catch (err) { setError(err.message) }
  }
  async function remove(id) {
    if (!confirm(`Delete this ${title.toLowerCase()} record?`)) return
    try { await api.request(`${endpoint}/${id}`, { method: 'DELETE' }); await load() } catch (err) { setError(err.message) }
  }
  async function pay(id, data) {
    try { await api.request(`${endpoint}/${id}/mark-paid`, { method: 'POST', body: JSON.stringify(data) }); setPaying(null); await load() } catch (err) { setError(err.message) }
  }

  const setFilter = (name, value) => { setFilterValues({ ...filterValues, [name]: value }); setPage(1) }

  return (
    <section className="page">
      <Toolbar title={title} canWrite={canWrite} onAdd={add} />
      {!!filters.length && <div className="filters">{filters.map((name) => {
        if (name === 'project_id') return <ProjectPicker api={api} projects={projects} key={name} value={filterValues[name]} onChange={(value) => setFilter(name, value)} allOption />
        if (name === 'financial_account_id') return <select aria-label="Received by" key={name} value={filterValues[name]} onChange={(e) => setFilter(name, e.target.value)}><option value="">All receivers</option>{financialAccounts.map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}</select>
        if (options[name]) return <select aria-label={label(name)} key={name} value={filterValues[name]} onChange={(e) => setFilter(name, e.target.value)}><option value="">All {label(name).toLowerCase()}</option>{options[name].map((value) => <option key={value}>{value}</option>)}</select>
        return <input key={name} aria-label={label(name)} type={name.includes('date') ? 'date' : 'search'} placeholder={name === 'search' ? `Search ${title.toLowerCase()}` : ''} value={filterValues[name]} onChange={(e) => setFilter(name, e.target.value)} />
      })}{Object.values(filterValues).some(Boolean) && <button onClick={() => { setFilterValues(Object.fromEntries(filters.map((name) => [name, '']))); setPage(1) }}>Clear</button>}</div>}
      {error && <div className="alert">{error}</div>}
      {loading ? <Loading label={`Loading ${title.toLowerCase()}`} /> : <Table rows={rows} columns={columns} actions={(row) => (
        <>
          {endpoint !== '/users' && <ActionButton label={row.source_type === 'Domain Billing' ? 'View domain billing record' : `View ${title.toLowerCase()} record`} icon={Eye} onClick={() => view(row)} />}
          {markPaid && canWrite && !Number(row.is_read_only) && <ActionButton label="Mark fee paid" icon={CheckCircle2} onClick={() => setPaying(row)} />}
          {canWrite && !Number(row.is_read_only) && !row.domain_billing_period_id && <ActionButton label={`Edit ${title.toLowerCase()} record`} icon={Pencil} onClick={() => setEditing(row)} />}
          {canWrite && !Number(row.is_read_only) && !row.domain_billing_period_id && <ActionButton label={`Delete ${title.toLowerCase()} record`} icon={Trash2} danger onClick={() => remove(row.id)} />}
        </>
      )} />}
      <Pagination value={pagination} onChange={setPage} />
      {editing && <Modal title={`${editing.id ? 'Edit' : 'Add'} ${title}`} onClose={() => setEditing(null)}>{error && <div className="modal-alert alert">{error}</div>}<DynamicForm api={api} initial={editing} fields={fields} options={options} projects={projects} financialAccounts={financialAccounts} onSubmit={save} submitting={saving} /></Modal>}
      {detail && <Modal title={detail.kind === 'domain' ? 'Domain Billing Record' : `${title} Record`} onClose={() => setDetail(null)} wide={detail.kind === 'domain'}>{detail.kind === 'domain' ? <DomainBillingDetail billing={detail.record} canWrite={false} onDeletePayment={() => {}} /> : <RecordDetail record={detail.record} />}</Modal>}
      {paying && <Modal title="Mark Fee Paid" onClose={() => setPaying(null)}>{error && <div className="modal-alert alert">{error}</div>}<FeePaidForm accounts={financialAccounts} onSubmit={(data) => pay(paying.id, data)} /></Modal>}
    </section>
  )
}

function DomainBilling({ api, canWrite }) {
  const [rows, setRows] = useState([])
  const [projects, setProjects] = useState([])
  const [accounts, setAccounts] = useState([])
  const [editing, setEditing] = useState(null)
  const [detail, setDetail] = useState(null)
  const [paying, setPaying] = useState(null)
  const [purchasing, setPurchasing] = useState(null)
  const emptyFilters = { search: '', project_id: '', payment_status: '', purchase_status: '', date_from: '', date_to: '' }
  const [filters, setFilters] = useState(emptyFilters)
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({ page: 1, pages: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const search = useDebouncedValue(filters.search)
  const query = new URLSearchParams({ ...filters, search, page: String(page), limit: '25' }).toString()

  const load = useCallback(() => {
    setLoading(true); setError('')
    return api.request(`/domain-billings?${query}`).then((data) => { setRows(data.rows || []); setPagination(data.pagination || { page: 1, pages: 1, total: data.length }) }).catch((err) => setError(err.message)).finally(() => setLoading(false))
  }, [api, query])
  useEffect(() => { load() }, [load])
  useEffect(() => {
    api.request('/projects?compact=1').then(setProjects).catch((err) => setError(err.message))
    api.request('/financial-accounts').then(setAccounts).catch((err) => setError(err.message))
  }, [api])

  const refreshAccounts = useCallback(() => api.request('/financial-accounts').then(setAccounts), [api])

  async function refreshDetail(id) {
    const record = await api.request(`/domain-billings/${id}`)
    setDetail(record)
    return record
  }
  async function saveQuote(form) {
    if (saving) return
    setSaving(true); setError('')
    try {
      await api.request(`/domain-billings${form.id ? `/${form.id}` : ''}`, { method: form.id ? 'PUT' : 'POST', body: JSON.stringify(form) })
      setEditing(null); await load()
    } catch (err) { setError(err.message) } finally { setSaving(false) }
  }
  async function recordPayment(id, form) {
    if (saving) return
    setSaving(true); setError('')
    try {
      await api.request(`/domain-billings/${id}/customer-payment`, { method: 'POST', body: JSON.stringify(form) })
      setPaying(null); await Promise.all([load(), refreshAccounts()]); if (detail?.id === id) await refreshDetail(id)
    } catch (err) { setError(err.message) } finally { setSaving(false) }
  }
  async function savePurchase(id, form) {
    if (saving) return
    setSaving(true); setError('')
    try {
      await api.request(`/domain-billings/${id}/purchase`, { method: 'POST', body: JSON.stringify(form) })
      setPurchasing(null); await Promise.all([load(), refreshAccounts()]); if (detail?.id === id) await refreshDetail(id)
    } catch (err) { setError(err.message) } finally { setSaving(false) }
  }
  async function reversePurchase(id) {
    if (!confirm('Reverse this registrar purchase and remove its linked expense and account transaction?')) return
    try { await api.request(`/domain-billings/${id}/purchase`, { method: 'DELETE' }); setPurchasing(null); await Promise.all([load(), refreshAccounts()]); if (detail?.id === id) await refreshDetail(id) } catch (err) { setError(err.message) }
  }
  async function remove(id) {
    if (!confirm('Delete this annual domain billing period?')) return
    try { await api.request(`/domain-billings/${id}`, { method: 'DELETE' }); setDetail(null); await load() } catch (err) { setError(err.message) }
  }
  async function removePayment(billingId, paymentId) {
    if (!confirm('Reverse this customer domain payment, its linked account transaction, and any linked receipt record?')) return
    try { await api.request(`/domain-billings/${billingId}/customer-payment/${paymentId}`, { method: 'DELETE' }); await Promise.all([load(), refreshAccounts()]); await refreshDetail(billingId) } catch (err) { setError(err.message) }
  }
  async function renew(id) {
    try { const created = await api.request(`/domain-billings/${id}/renew`, { method: 'POST', body: '{}' }); await load(); setEditing(await api.request(`/domain-billings/${created.id}`)) } catch (err) { setError(err.message) }
  }
  const setFilter = (key, value) => { setFilters({ ...filters, [key]: value }); setPage(1) }
  const columns = [{ key: 'project_name' }, { key: 'domain_name', label: 'Domain' }, { key: 'period_label', label: 'Period' }, { key: 'customer_renewal_date' }, { key: 'coverage_end_date', label: 'Registrar Expiry' }, { key: 'customer_price' }, { key: 'customer_paid_amount', label: 'Paid' }, { key: 'customer_balance_amount', label: 'Balance' }, { key: 'customer_payment_status', label: 'Payment' }, { key: 'effective_purchase_status', label: 'Purchase' }]

  return <section className="page domain-billing-page">
    <Toolbar title="Domain Billing" canWrite={canWrite} onAdd={() => { setError(''); setEditing({ project_id: '', domain_name: '', period_label: '', quote_date: today(), customer_price: '', customer_due_date: '', purchase_status: 'Quoted', reminder_days_before_due: 30, notes: '' }) }} />
    <div className="filters"><input type="search" placeholder="Search domain, project or registrar" value={filters.search} onChange={(e) => setFilter('search', e.target.value)} /><ProjectPicker api={api} projects={projects} value={filters.project_id} onChange={(value) => setFilter('project_id', value)} allOption /><select value={filters.payment_status} onChange={(e) => setFilter('payment_status', e.target.value)}><option value="">All payment states</option>{['Not Priced','Unpaid','Partially Paid','Paid'].map((value) => <option key={value}>{value}</option>)}</select><select value={filters.purchase_status} onChange={(e) => setFilter('purchase_status', e.target.value)}><option value="">All purchase states</option>{['Not Purchased','Active','Expired','Cancelled'].map((value) => <option key={value}>{value}</option>)}</select><input aria-label="Domain quote date from" type="date" value={filters.date_from} onChange={(e) => setFilter('date_from', e.target.value)} /><input aria-label="Domain quote date to" type="date" value={filters.date_to} onChange={(e) => setFilter('date_to', e.target.value)} />{Object.values(filters).some(Boolean) && <button onClick={() => { setFilters(emptyFilters); setPage(1) }}>Clear</button>}</div>
    {error && <div className="alert">{error}</div>}
    {loading ? <Loading label="Loading domain billing" /> : <Table rows={rows} columns={columns} actions={(row) => <>
      <ActionButton label="View annual domain record" icon={Eye} onClick={() => refreshDetail(row.id)} />
      {canWrite && Number(row.customer_paid_amount) > 0 && <ActionButton label="Reverse customer domain payment" icon={RotateCcw} onClick={() => refreshDetail(row.id)} />}
      {canWrite && <ActionButton label="Edit quote" icon={Pencil} onClick={() => setEditing(row)} />}
      {canWrite && row.purchase_status !== 'Cancelled' && Number(row.customer_balance_amount) > 0 && Number(row.customer_price) > 0 && <ActionButton label="Record customer domain payment" icon={CircleDollarSign} onClick={() => setPaying(row)} />}
      {canWrite && row.purchase_status !== 'Cancelled' && <ActionButton label={Number(row.is_registrar_carryover) === 1 ? 'Extend registrar coverage' : row.purchase_status === 'Purchased' ? 'Edit registrar purchase' : 'Purchase domain'} icon={ShoppingCart} onClick={() => setPurchasing(row)} />}
      {canWrite && row.purchase_status === 'Purchased' && <ActionButton label="Create next annual period" icon={CalendarPlus} onClick={() => renew(row.id)} />}
      {canWrite && <ActionButton label="Delete annual domain record" icon={Trash2} danger onClick={() => remove(row.id)} />}
    </>} />}
    <Pagination value={pagination} onChange={setPage} />
    {editing && <Modal title={`${editing.id ? 'Edit' : 'Add'} Annual Domain Period`} onClose={() => setEditing(null)}>{error && <div className="modal-alert alert">{error}</div>}<DomainQuoteForm api={api} initial={editing} projects={projects} onSubmit={saveQuote} submitting={saving} /></Modal>}
    {paying && <Modal title="Record Customer Domain Payment" onClose={() => setPaying(null)}>{error && <div className="modal-alert alert">{error}</div>}<DomainPaymentForm billing={paying} accounts={accounts} onSubmit={(form) => recordPayment(paying.id, form)} submitting={saving} /></Modal>}
    {purchasing && <Modal title={Number(purchasing.is_registrar_carryover) === 1 ? 'Extend Registrar Coverage' : purchasing.purchase_status === 'Purchased' ? 'Edit Registrar Purchase' : 'Purchase Domain'} onClose={() => setPurchasing(null)}>{error && <div className="modal-alert alert">{error}</div>}<DomainPurchaseForm billing={purchasing} accounts={accounts} onSubmit={(form) => savePurchase(purchasing.id, form)} onReverse={purchasing.purchase_status === 'Purchased' && Number(purchasing.is_registrar_carryover) !== 1 ? () => reversePurchase(purchasing.id) : null} submitting={saving} /></Modal>}
    {detail && <Modal title="Annual Domain Details" wide onClose={() => setDetail(null)}><DomainBillingDetail billing={detail} canWrite={canWrite} onDeletePayment={(paymentId) => removePayment(detail.id, paymentId)} /></Modal>}
  </section>
}

function DomainQuoteForm({ api, initial, projects, onSubmit, submitting }) {
  const [form, setForm] = useState(initial)
  const set = (key, value) => setForm({ ...form, [key]: value })
  return <form className="form-grid" onSubmit={(event) => { event.preventDefault(); onSubmit(form) }}>
    <ProjectPicker api={api} projects={projects} value={form.project_id} onChange={(value) => set('project_id', value)} required />
    <label>Domain Name<input placeholder="Can be added before purchase" value={form.domain_name || ''} onChange={(e) => set('domain_name', e.target.value)} /></label>
    <label>Period Label<input placeholder="First year or 2026-2027" value={form.period_label || ''} onChange={(e) => set('period_label', e.target.value)} /></label>
    <label>Quote Date<input required type="date" value={form.quote_date || ''} onChange={(e) => set('quote_date', e.target.value)} /></label>
    <label>Customer Domain Price<input required type="number" min="0" step="0.01" value={form.customer_price ?? ''} onChange={(e) => set('customer_price', e.target.value)} /></label>
    <label>Customer Due Date<input type="date" value={form.customer_due_date || ''} onChange={(e) => set('customer_due_date', e.target.value)} /></label>
    <label>Reminder Days<input required type="number" min="0" max="365" step="1" value={form.reminder_days_before_due ?? 30} onChange={(e) => set('reminder_days_before_due', e.target.value)} /></label>
    <label>Status<select value={form.purchase_status === 'Purchased' ? 'Quoted' : form.purchase_status || 'Quoted'} disabled={form.purchase_status === 'Purchased'} onChange={(e) => set('purchase_status', e.target.value)}><option>Quoted</option><option>Cancelled</option></select></label>
    <label className="full">Notes<textarea value={form.notes || ''} onChange={(e) => set('notes', e.target.value)} /></label>
    <div className="form-actions"><button className="primary" disabled={submitting}>{submitting ? 'Saving...' : 'Save Domain Period'}</button></div>
  </form>
}

function DomainPaymentForm({ billing, accounts, onSubmit, submitting }) {
  const [form, setForm] = useState({ payment_date: today(), amount: billing.customer_balance_amount, is_historical: 0, financial_account_id: '', reference_number: '', notes: '' })
  return <form className="form-grid" onSubmit={(event) => { event.preventDefault(); onSubmit(form) }}><div className="full inline-summary"><span>Customer price <b>{currency(billing.customer_price)}</b></span><span>Remaining <b>{currency(billing.customer_balance_amount)}</b></span></div><label>Date<input required type="date" value={form.payment_date} onChange={(e) => setForm({ ...form, payment_date: e.target.value })} /></label><label>Amount<input required type="number" min="0.01" max={billing.customer_balance_amount} step="0.01" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} /></label><label className="check-row"><input type="checkbox" checked={Boolean(form.is_historical)} onChange={(e) => setForm({ ...form, is_historical: e.target.checked ? 1 : 0, financial_account_id: '' })} />Historical payment</label>{!form.is_historical && <label>Received By<select required value={form.financial_account_id} onChange={(e) => setForm({ ...form, financial_account_id: e.target.value })}><option value="">Choose account</option>{accounts.filter((account) => account.status === 'Active').map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}</select></label>}<label>Reference<input value={form.reference_number} onChange={(e) => setForm({ ...form, reference_number: e.target.value })} /></label><label className="full">Notes<textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} /></label><div className="form-actions"><button className="primary" disabled={submitting}>{submitting ? 'Saving...' : 'Record Payment'}</button></div></form>
}

function DomainPurchaseForm({ billing, accounts, onSubmit, onReverse, submitting }) {
  const initialPurchaseDate = billing.purchase_date || today()
  const [form, setForm] = useState({ purchase_date: initialPurchaseDate, customer_renewal_date: billing.customer_renewal_date || addYearClamped(initialPurchaseDate), coverage_end_date: billing.coverage_end_date || addYearClamped(initialPurchaseDate), actual_registrar_cost: billing.actual_registrar_cost || '', is_historical_purchase: Number(billing.is_historical_purchase || 0), financial_account_id: billing.paid_from_account_id || '', registrar_provider: billing.registrar_provider || '', registrar_reference: billing.registrar_reference || '' })
  const availableAccounts = accounts.filter((account) => account.status === 'Active' || String(account.id) === String(form.financial_account_id))
  const setPurchaseDate = (value) => {
    const previousDefault = addYearClamped(form.purchase_date)
    const nextDefault = addYearClamped(value)
    setForm({ ...form, purchase_date: value, customer_renewal_date: !form.customer_renewal_date || form.customer_renewal_date === previousDefault ? nextDefault : form.customer_renewal_date, coverage_end_date: !form.coverage_end_date || form.coverage_end_date === previousDefault ? nextDefault : form.coverage_end_date })
  }
  return <form className="form-grid" onSubmit={(event) => { event.preventDefault(); onSubmit(form) }}><div className="full info-message">Customer renewal controls yearly billing. Registrar expiry is the real date the domain registration ends, including multi-year purchases.</div><label>Purchase Date<input required type="date" value={form.purchase_date} onChange={(e) => setPurchaseDate(e.target.value)} /></label><label>Customer Renewal Date<input required type="date" value={form.customer_renewal_date} onChange={(e) => setForm({ ...form, customer_renewal_date: e.target.value })} /></label><label>Registrar Expiry Date<input required type="date" value={form.coverage_end_date} onChange={(e) => setForm({ ...form, coverage_end_date: e.target.value })} /></label><label>Actual Registrar Cost<input required type="number" min="0.01" step="0.01" value={form.actual_registrar_cost} onChange={(e) => setForm({ ...form, actual_registrar_cost: e.target.value })} /></label><label className="check-row"><input type="checkbox" checked={Boolean(form.is_historical_purchase)} onChange={(e) => setForm({ ...form, is_historical_purchase: e.target.checked ? 1 : 0, financial_account_id: '' })} />Already purchased before financial tracking</label>{!form.is_historical_purchase && <label>Paid From<select required value={form.financial_account_id} onChange={(e) => setForm({ ...form, financial_account_id: e.target.value })}><option value="">Choose account</option>{availableAccounts.map((account) => <option key={account.id} value={account.id}>{account.name}{account.status === 'Inactive' ? ' (Inactive)' : ''} - {currency(account.balance)}</option>)}</select></label>}<label>Registrar<input value={form.registrar_provider} onChange={(e) => setForm({ ...form, registrar_provider: e.target.value })} /></label><label className="full">Registrar Reference<input value={form.registrar_reference} onChange={(e) => setForm({ ...form, registrar_reference: e.target.value })} /></label><div className="full info-message">{form.is_historical_purchase ? 'The registrar cost is kept as a project expense, but no financial account balance is changed.' : 'Saving creates one linked Domain Purchase expense and one financial Use from the selected account.'}</div><div className="form-actions">{onReverse && <button type="button" className="danger-button" onClick={onReverse}><RotateCcw size={16} />Reverse Purchase</button>}<button className="primary" disabled={submitting}>{submitting ? 'Saving...' : 'Save Purchase'}</button></div></form>
}

function DomainBillingDetail({ billing, canWrite, onDeletePayment }) {
  return <div className="domain-detail"><div className="summary-grid domain-summary"><Card label="Customer Price" value={currency(billing.customer_price)} /><Card label="Customer Paid" value={currency(billing.customer_paid_amount)} /><Card label="Customer Balance" value={currency(billing.customer_balance_amount)} /><Card label="Registrar Cost" value={currency(billing.actual_registrar_cost)} /><Card label="Domain Profit" value={currency(billing.realized_domain_profit)} /></div><Panel title="Annual Period"><RecordDetail record={Object.fromEntries(Object.entries(billing).filter(([key]) => !['payments','purchase_expense','purchase_transaction'].includes(key)))} /></Panel><Panel title="Customer Payments"><PaginatedTable rows={billing.payments || []} columns={[{ key: 'payment_date' }, { key: 'amount' }, { key: 'is_historical', label: 'Source' }, { key: 'financial_account_name', label: 'Received By' }, { key: 'reference_number' }]} actions={canWrite ? (payment) => <button className="danger-button" onClick={() => onDeletePayment(payment.id)}><RotateCcw size={15} />Reverse Payment</button> : null} /></Panel>{billing.purchase_expense && <Panel title="Registrar Accounting"><div className="linked-accounting"><div><strong>Project Expense</strong><span>{billing.purchase_expense.expense_date} · {currency(billing.purchase_expense.amount)}</span></div>{billing.purchase_transaction ? <div><strong>Financial Use</strong><span>{billing.purchase_transaction.from_account_name || billing.paid_from_account_name} · {currency(billing.purchase_transaction.amount)}</span></div> : <div><strong>Account Movement</strong><span>Historical purchase - none created</span></div>}</div></Panel>}</div>
}

function FeePaidForm({ accounts, onSubmit }) {
  const [form, setForm] = useState({ create_payment: false, financial_account_id: '' })
  return <form className="form-grid" onSubmit={(event) => { event.preventDefault(); onSubmit(form) }}><label className="check-row"><input type="checkbox" checked={form.create_payment} onChange={(event) => setForm({ ...form, create_payment: event.target.checked, financial_account_id: '' })} />Record customer payment</label>{form.create_payment && <label className="full">Received By<select required value={form.financial_account_id} onChange={(event) => setForm({ ...form, financial_account_id: event.target.value })}><option value="">Choose receiver</option>{accounts.filter((account) => account.status === 'Active').map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}</select></label>}<div className="form-actions"><button className="primary">Mark Paid</button></div></form>
}

function RecordDetail({ record }) {
  const hidden = new Set(['password_hash'])
  return <div className="detail-grid record-detail">{Object.entries(record).filter(([key, value]) => !hidden.has(key) && value !== null && typeof value !== 'object').map(([key, value]) => <p key={key}><strong>{label(key)}</strong><span>{key.startsWith('is_') ? (Number(value) === 1 ? 'Yes' : 'No') : key.includes('amount') || key.includes('price') || key.includes('balance') ? currency(value) : String(value === '' ? '-' : value)}</span></p>)}</div>
}

function Invoices({ api, canWrite }) {
  const [rows, setRows] = useState([])
  const [projects, setProjects] = useState([])
  const [settings, setSettings] = useState({})
  const [editing, setEditing] = useState(null)
  const [preview, setPreview] = useState(null)
  const [filters, setFilters] = useState({ search: '', project_id: '', status: '', invoice_type: '', date_from: '', date_to: '' })
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({ page: 1, pages: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const debouncedInvoiceSearch = useDebouncedValue(filters.search)
  const invoiceListQuery = new URLSearchParams({ ...filters, search: debouncedInvoiceSearch, page: String(page), limit: '25' }).toString()

  const load = useCallback(async () => {
    try {
      setLoading(true); setError('')
      const invoices = await api.request(`/invoices?${invoiceListQuery}`)
      setRows(invoices.rows || invoices)
      setPagination(invoices.pagination || { page: 1, pages: 1, total: invoices.length })
    } catch (err) { setError(err.message) } finally { setLoading(false) }
  }, [api, invoiceListQuery])
  useEffect(() => { load() }, [load])
  useEffect(() => {
    Promise.all([api.request('/projects?compact=1'), api.request('/settings')])
      .then(([projectRows, settingRows]) => { setProjects(projectRows); setSettings(Object.fromEntries(settingRows.map((row) => [row.setting_key, row.setting_value]))) })
      .catch((err) => setError(err.message))
  }, [api])

  async function create() {
    try {
      setError('')
      const next = await api.request('/invoices/next-number')
      setEditing({ invoice_number: next.invoice_number, project_id: '', invoice_date: today(), due_date: '', invoice_type: 'Project Invoice', discount_amount: 0, tax_amount: 0, paid_amount: 0, status: 'Draft', notes: '', items: [{ description: 'Project service', quantity: 1, unit_price: 0 }] })
    } catch (err) { setError(err.message) }
  }
  async function save(data) {
    if (saving) return
    setSaving(true)
    try {
      const result = await api.request(`/invoices${data.id ? `/${data.id}` : ''}`, { method: data.id ? 'PUT' : 'POST', body: JSON.stringify(data) })
      setEditing(null); await load(); setPreview(await api.request(`/invoices/${result.id}`))
    } catch (err) { setError(err.message) } finally { setSaving(false) }
  }
  async function show(id) { try { setPreview(await api.request(`/invoices/${id}`)) } catch (err) { setError(err.message) } }
  async function edit(id) { try { setEditing(await api.request(`/invoices/${id}`)) } catch (err) { setError(err.message) } }
  async function remove(id) {
    if (!confirm('Delete this invoice?')) return
    try { await api.request(`/invoices/${id}`, { method: 'DELETE' }); await load() } catch (err) { setError(err.message) }
  }

  return <section className="page">
    <Toolbar title="Invoices" canWrite={canWrite} onAdd={create} />
    <div className="filters"><input type="search" placeholder="Search invoices" value={filters.search} onChange={(e) => { setFilters({ ...filters, search: e.target.value }); setPage(1) }} /><ProjectPicker api={api} projects={projects} value={filters.project_id} onChange={(value) => { setFilters({ ...filters, project_id: value }); setPage(1) }} allOption /><select value={filters.invoice_type} onChange={(e) => { setFilters({ ...filters, invoice_type: e.target.value }); setPage(1) }}><option value="">All invoice types</option>{invoiceTypes.map((type) => <option key={type}>{type}</option>)}</select><select value={filters.status} onChange={(e) => { setFilters({ ...filters, status: e.target.value }); setPage(1) }}><option value="">All statuses</option>{['Draft','Sent','Partially Paid','Paid','Overdue','Cancelled'].map((status) => <option key={status}>{status}</option>)}</select><input aria-label="Invoice date from" type="date" value={filters.date_from} onChange={(e) => { setFilters({ ...filters, date_from: e.target.value }); setPage(1) }} /><input aria-label="Invoice date to" type="date" value={filters.date_to} onChange={(e) => { setFilters({ ...filters, date_to: e.target.value }); setPage(1) }} />{Object.values(filters).some(Boolean) && <button onClick={() => { setFilters({ search: '', project_id: '', status: '', invoice_type: '', date_from: '', date_to: '' }); setPage(1) }}>Clear</button>}</div>
    {error && <div className="alert">{error}</div>}
    {loading ? <Loading label="Loading invoices" /> : <Table rows={rows} columns={invoiceColumns} actions={(row) => <><ActionButton label="Preview invoice" icon={Eye} onClick={() => show(row.id)} />{canWrite && <ActionButton label="Edit invoice" icon={Pencil} onClick={() => edit(row.id)} />}{canWrite && <ActionButton label="Delete invoice" icon={Trash2} danger onClick={() => remove(row.id)} />}</>} />}
    <Pagination value={pagination} onChange={setPage} />
    {editing && <FullScreenWorkspace title={editing.id ? `Edit ${editing.invoice_number}` : 'Generate Invoice'} onClose={() => setEditing(null)}>{error && <div className="modal-alert alert">{error}</div>}<InvoiceEditor api={api} initial={editing} projects={projects} settings={settings} onSubmit={save} submitting={saving} /></FullScreenWorkspace>}
    {preview && <FullScreenWorkspace title={preview.invoice_number} onClose={() => setPreview(null)} actions={<button className="primary" onClick={() => window.print()}>Save Softcopy PDF</button>}><div className="invoice-full-preview"><InvoiceA4 invoice={preview} project={preview} settings={preview.settings || settings} /></div></FullScreenWorkspace>}
  </section>
}

function InvoiceEditor({ api, initial, projects, settings, onSubmit, submitting }) {
  const [form, setForm] = useState(initial)
  const [items, setItems] = useState(initial.items?.length ? initial.items : [{ description: 'Project service', quantity: 1, unit_price: 0 }])
  const [selectedProject, setSelectedProject] = useState(projects.find((row) => String(row.id) === String(initial.project_id)) || initial)
  const project = selectedProject || initial
  const subtotal = items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0)
  const total = subtotal - Number(form.discount_amount || 0) + Number(form.tax_amount || 0)
  const previewInvoice = { ...form, subtotal, total_amount: total, balance_amount: total - Number(form.paid_amount || 0), items }

  useEffect(() => {
    if (initial.id || !form.invoice_date) return undefined
    let active = true
    api.request(`/invoices/next-number?invoice_date=${encodeURIComponent(form.invoice_date)}`)
      .then((next) => { if (active) setForm((current) => ({ ...current, invoice_number: next.invoice_number })) })
      .catch(() => {})
    return () => { active = false }
  }, [api, form.invoice_date, initial.id])

  function chooseProject(projectId, selectedOption) {
    const selected = selectedOption || projects.find((row) => String(row.id) === String(projectId))
    setSelectedProject(selected || null)
    setForm({ ...form, project_id: projectId, ...(!initial.id && selected ? { notes: selected.notes || form.notes || '' } : {}) })
    if (selected && !initial.id) {
      const amount = Math.max(Number(selected.remaining_balance || selected.total_payable || 0), 0)
      setItems([{ description: `${selected.project_name} - ${selected.project_type || 'Project service'}`, quantity: 1, unit_price: amount }])
    }
  }
  function updateItem(index, key, value) { setItems(items.map((item, itemIndex) => itemIndex === index ? { ...item, [key]: value } : item)) }

  return <div className="invoice-workspace">
    <form className="invoice-editor" onSubmit={(event) => { event.preventDefault(); onSubmit({ ...form, items }) }}>
      <div className="invoice-editor-grid">
        <label>Invoice Number<input value={form.invoice_number || ''} readOnly /></label>
        <ProjectPicker api={api} projects={projects} value={form.project_id} onChange={chooseProject} required />
        <label>Invoice Type<select value={form.invoice_type} onChange={(event) => setForm({ ...form, invoice_type: event.target.value })}>{invoiceTypes.map((type) => <option key={type}>{type}</option>)}</select></label>
        <label>Issue Date<input type="date" value={form.invoice_date || ''} onChange={(event) => setForm({ ...form, invoice_date: event.target.value })} required /></label>
        <label>Due Date<input type="date" value={form.due_date || ''} onChange={(event) => setForm({ ...form, due_date: event.target.value })} /></label>
      </div>
      <fieldset><legend>Invoice Items</legend>{items.map((item, index) => <div className="item-row" key={index}><input aria-label="Description" placeholder="Description" value={item.description} onChange={(event) => updateItem(index, 'description', event.target.value)} required /><input aria-label="Quantity" type="number" min="0.01" step="0.01" value={item.quantity} onChange={(event) => updateItem(index, 'quantity', event.target.value)} /><input aria-label="Unit price" type="number" min="0" step="0.01" value={item.unit_price} onChange={(event) => updateItem(index, 'unit_price', event.target.value)} /><button type="button" aria-label="Remove item" onClick={() => setItems(items.filter((_, itemIndex) => itemIndex !== index))}>Remove</button></div>)}<button type="button" onClick={() => setItems([...items, { description: '', quantity: 1, unit_price: 0 }])}>Add Item</button></fieldset>
      <details className="optional-fields"><summary>Adjustments and notes</summary><div className="invoice-editor-grid"><label>Discount<input type="number" min="0" value={form.discount_amount || 0} onChange={(event) => setForm({ ...form, discount_amount: event.target.value })} /></label><label>Tax / Additional Charge<input type="number" min="0" value={form.tax_amount || 0} onChange={(event) => setForm({ ...form, tax_amount: event.target.value })} /></label><label>Already Paid<input type="number" min="0" value={form.paid_amount || 0} onChange={(event) => setForm({ ...form, paid_amount: event.target.value })} /></label><label className="full">Notes<textarea value={form.notes || ''} onChange={(event) => setForm({ ...form, notes: event.target.value })} /></label></div></details>
      <div className="form-actions"><button className="primary" disabled={submitting}>{submitting ? 'Saving…' : 'Save Invoice'}</button></div>
    </form>
    <div className="invoice-preview-pane"><span className="a4-label">Live A4 preview · 210 × 297 mm</span><div className="a4-scroll"><InvoiceA4 invoice={previewInvoice} project={project} settings={settings} /></div></div>
  </div>
}

function InvoiceA4({ invoice, project = {}, settings = {}, design: suppliedDesign, designer = null }) {
  const items = invoice.items || []
  const subtotal = Number(invoice.subtotal ?? items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0))
  const total = Number(invoice.total_amount ?? subtotal - Number(invoice.discount_amount || 0) + Number(invoice.tax_amount || 0))
  const paid = Number(invoice.paid_amount || 0)
  const balance = Number(invoice.balance_amount ?? total - paid)
  const money = (value) => `${Number(value || 0).toLocaleString()} ${project.currency || settings.currency || 'MMK'}`
  const design = suppliedDesign || invoiceDesignFrom(settings.invoice_design)
  const columnWidths = String(design.table.columnWidths || '10,61,9,20').split(',').map((value) => Math.max(1, Number(value) || 1))
  const pageStyle = { fontFamily: design.page.fontFamily, fontSize: `${design.page.fontSize}px`, lineHeight: design.page.lineHeight, color: design.page.color, background: design.page.background, '--invoice-accent': design.page.accent, '--invoice-table-head': design.table.headerBackground, '--invoice-table-head-text': design.table.headerColor, '--invoice-table-border': design.table.borderColor, '--invoice-table-border-width': `${design.table.borderWidth}px`, '--invoice-table-padding': `${design.table.cellPadding}px`, '--invoice-table-font-size': `${design.table.fontSize}px` }
  const block = (id, content) => <InvoiceBlock key={id} id={id} config={design.blocks[id]} designer={designer}>{content}</InvoiceBlock>
  return <article className={`invoice-a4${designer ? ' invoice-design-mode' : ''}`} style={pageStyle}>
    <div className="invoice-a4-inner designed">
      {block('header', <header className="invoice-head"><div><h2>{settings.company_name || 'Your Company Name'}</h2><p>{settings.company_tagline || 'Building Solutions. Empowering Growth.'}</p></div><div className="invoice-title"><h2>INVOICE</h2><p>Payment request</p></div></header>)}
      {block('meta', <section className="invoice-meta"><div><small>Billed to</small><strong>{project.customer_company_name || project.contact_person || 'Choose a project'}</strong></div><div><small>Invoice no</small><strong>#{invoice.invoice_number || 'Pending'}</strong><small>Issue date</small><strong>{invoice.invoice_date || today()}</strong>{invoice.due_date && <p>Due {invoice.due_date}</p>}</div></section>)}
      {block('callout', <section className="invoice-callout"><p>{balance > 0 ? 'Please pay the amount below to complete this payment stage.' : 'This invoice has been fully paid.'}</p><strong>Payment Due Now: {money(Math.max(balance, 0))}</strong></section>)}
      {block('table', <table className={`invoice-line-table${design.table.striped ? ' striped' : ''}`}><colgroup>{columnWidths.map((width, index) => <col key={index} style={{ width: `${width}%` }} />)}</colgroup><thead><tr><th>No.</th><th>Description</th><th>Qty</th><th>Amount</th></tr></thead><tbody>{items.map((item, index) => <tr key={index}><td>{index + 1}</td><td>{item.description}</td><td>{Number(item.quantity || 0)}</td><td>{money(Number(item.quantity || 0) * Number(item.unit_price || 0))}</td></tr>)}</tbody></table>)}
      {block('summary', <section className="invoice-summary"><p><span>Services subtotal:</span><strong>{money(subtotal)}</strong></p>{Number(invoice.discount_amount || 0) > 0 && <p><span>Discount:</span><strong>- {money(invoice.discount_amount)}</strong></p>}{Number(invoice.tax_amount || 0) > 0 && <p><span>Additional charge:</span><strong>+ {money(invoice.tax_amount)}</strong></p>}<p><span>Total invoice:</span><strong>{money(total)}</strong></p>{paid > 0 && <p><span>Already paid:</span><strong>{money(paid)}</strong></p>}<div><span>Remaining balance</span><strong>{money(Math.max(balance, 0))}</strong></div></section>)}
      {block('notes', <p className="invoice-note">{invoice.notes || 'Thank you for your business.'}</p>)}
      {block('footer', <footer className="invoice-footer"><div><h3>PAYMENT METHODS</h3><strong>{settings.payment_method || 'Update payment method'}</strong><p>{settings.payment_account || ''}</p></div><div><h3>CONTACT US</h3><p>{[settings.company_phone, settings.company_telegram, settings.company_email, settings.company_website].filter(Boolean).join(' · ') || 'Update company contact details in Settings'}</p></div></footer>)}
    </div>
  </article>
}

function InvoiceBlock({ id, config, designer, children }) {
  if (!config?.visible) return null
  const selected = designer?.selected === id
  function startDrag(event) {
    if (!designer || event.button !== 0) return
    event.preventDefault(); event.stopPropagation(); designer.onSelect(id)
    const page = event.currentTarget.closest('.invoice-a4').getBoundingClientRect()
    const startX = event.clientX; const startY = event.clientY; const originX = Number(config.x); const originY = Number(config.y)
    const move = (moveEvent) => designer.onChange(id, { x: Math.max(0, Math.min(100 - Number(config.width), originX + ((moveEvent.clientX - startX) / page.width) * 100)), y: Math.max(0, Math.min(98, originY + ((moveEvent.clientY - startY) / page.height) * 100)) })
    const stop = () => { document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', stop) }
    document.addEventListener('pointermove', move); document.addEventListener('pointerup', stop)
  }
  const style = { left: `${config.x}%`, top: `${config.y}%`, width: `${config.width}%`, minHeight: `${config.minHeight || 0}px`, padding: `${config.padding}px`, margin: `${config.margin}px`, textAlign: config.textAlign, fontFamily: config.fontFamily, fontSize: `${config.fontSize}px`, fontWeight: config.fontWeight, lineHeight: config.lineHeight, color: config.color, background: config.background, zIndex: selected ? 20 : 1 }
  return <div className={`invoice-block invoice-block-${id}${selected ? ' selected' : ''}`} style={style} onPointerDown={startDrag} onClick={(event) => { if (designer) { event.stopPropagation(); designer.onSelect(id) } }} role={designer ? 'button' : undefined} tabIndex={designer ? 0 : undefined} aria-label={designer ? `Edit ${invoiceBlockNames[id]}` : undefined}>{children}</div>
}

function DynamicForm({ api, initial, fields, options, projects, financialAccounts = [], onSubmit, submitting }) {
  const [form, setForm] = useState(initial)
  const [items, setItems] = useState(initial.items || [{ description: 'Project service', quantity: 1, unit_price: 0 }])
  const [paymentOptions, setPaymentOptions] = useState([])
  const needsPayment = fields.some((field) => field.name === 'payment_id')
  const set = (key, value) => setForm({ ...form, [key]: value })

  useEffect(() => {
    if (!needsPayment || !form.project_id) { setPaymentOptions([]); return }
    api.request(`/payments?compact=1&project_id=${encodeURIComponent(form.project_id)}`).then(setPaymentOptions).catch(() => setPaymentOptions([]))
  }, [api, form.project_id, needsPayment])

  function submit(e) {
    e.preventDefault()
    onSubmit({ ...form, ...(fields.some((field) => field.name === 'items') ? { items } : {}) })
  }

  return (
    <form className="form-grid" onSubmit={submit}>
      {fields.filter((field) => field.name !== 'items' && !field.hidden).map((field) => {
        if (field.name === 'project_id') {
          return <ProjectPicker api={api} key={field.name} label={field.label || label(field.name)} projects={projects} value={form.project_id} onChange={(value) => setForm({ ...form, project_id: value, ...(fields.some((item) => item.name === 'payment_id') ? { payment_id: '' } : {}) })} required />
        }
        if (field.name === 'payment_id') {
          const available = paymentOptions
          return <label key={field.name}>Payment<select value={form.payment_id || ''} onChange={(e) => {
            const payment = available.find((row) => String(row.id) === e.target.value)
            const project = projects.find((row) => String(row.id) === String(form.project_id))
            setForm({ ...form, payment_id: e.target.value, amount: payment?.available_amount ?? payment?.amount ?? form.amount, payment_method: payment?.payment_method || form.payment_method, received_from: project?.customer_company_name || form.received_from })
          }} required disabled={!form.project_id}><option value="">Choose payment</option>{available.map((payment) => <option key={payment.id} value={payment.id}>{payment.payment_date} - {currency(payment.available_amount ?? payment.amount)} available ({payment.payment_type})</option>)}</select></label>
        }
        if (field.name === 'financial_account_id') return Number(form.is_historical) === 1 ? null : <label key={field.name}>Received By<select required value={form.financial_account_id || ''} onChange={(e) => set('financial_account_id', e.target.value)}><option value="">Choose receiver</option>{financialAccounts.filter((account) => account.status === 'Active' || String(account.id) === String(form.financial_account_id)).map((account) => <option key={account.id} value={account.id}>{account.name}{account.status === 'Inactive' ? ' (Inactive)' : ''}</option>)}</select></label>
        if (field.name === 'receipt_number') return <label key={field.name}>Receipt Number<input value={form.receipt_number || 'Assigned on save'} readOnly /></label>
        const requiredNames = ['project_id','payment_id','payment_date','expense_date','receipt_date','next_due_date','amount','payment_type','payment_method','expense_category','fee_name','fee_type','billing_cycle','name','email','role','status']
        const isRequired = field.name === 'password' ? !initial.id : requiredNames.includes(field.name)
        return fieldControl(field.label || field.name, form[field.name] ?? field.default ?? '', (value) => set(field.name, value), options[field.name], field.type, isRequired)
      })}
      {fields.some((field) => field.name === 'items') && (
        <fieldset className="full">
          <legend>Invoice Items</legend>
          {items.map((item, index) => (
            <div className="item-row" key={index}>
              <input placeholder="Description" value={item.description} onChange={(e) => setItems(items.map((x, i) => i === index ? { ...x, description: e.target.value } : x))} />
              <input type="number" min="0" step="0.01" value={item.quantity} onChange={(e) => setItems(items.map((x, i) => i === index ? { ...x, quantity: e.target.value } : x))} />
              <input type="number" min="0" step="0.01" value={item.unit_price} onChange={(e) => setItems(items.map((x, i) => i === index ? { ...x, unit_price: e.target.value } : x))} />
              <button type="button" onClick={() => setItems(items.filter((_, i) => i !== index))}>Remove</button>
            </div>
          ))}
          <button type="button" onClick={() => setItems([...items, { description: '', quantity: 1, unit_price: 0 }])}>Add Item</button>
        </fieldset>
      )}
      <div className="form-actions"><button className="primary" disabled={submitting}>{submitting ? 'Saving…' : 'Save'}</button></div>
    </form>
  )
}

function Reminders({ api, onNavigate, canWrite }) {
  const [data, setData] = useState({ groups: { due_today: [], due_this_week: [], overdue: [], upcoming: [] }, counts: {} })
  const [pages, setPages] = useState({ due_today: 1, due_this_week: 1, overdue: 1, upcoming: 1 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const load = useCallback(() => {
    const query = new URLSearchParams({ limit: '10', ...Object.fromEntries(Object.entries(pages).map(([key, value]) => [`${key}_page`, value])) })
    setLoading(true)
    setError('')
    return api.request(`/reminders?${query}`).then((result) => {
      setData(result)
      setPages((current) => {
        const corrected = Object.fromEntries(Object.keys(current).map((key) => [key, result.pagination?.[key]?.page || 1]))
        return Object.keys(current).some((key) => current[key] !== corrected[key]) ? corrected : current
      })
    }).catch((err) => setError(err.message)).finally(() => setLoading(false))
  }, [api, pages])
  useEffect(() => { load() }, [load])
  async function resolve(row) {
    try { await api.request('/reminders/resolve', { method: 'POST', body: JSON.stringify(row) }); await load() } catch (err) { setError(err.message) }
  }
  const columns = [{ key: 'reminder_type', label: 'Type' }, { key: 'project_name' }, { key: 'customer_company_name', label: 'Customer' }, { key: 'amount' }, { key: 'due_date' }, { key: 'status' }]
  if (loading) return <section className="page"><Loading label="Loading reminders" /></section>
  return <section className="page reminders-page">
    {error && <div className="alert">{error}</div>}
    {[['due_today','Due Today'],['due_this_week','Due This Week'],['overdue','Overdue'],['upcoming','Upcoming Renewals']].map(([key, title]) => <Panel key={key} title={`${title} (${data.counts?.[key] || 0})`}><Table rows={data.groups?.[key] || []} columns={columns} actions={(row) => <><button onClick={() => onNavigate('Projects')}>View Project</button>{canWrite && row.source_type === 'project' && <button onClick={() => onNavigate('Payments')}>Add Payment</button>}{canWrite && <button onClick={() => resolve(row)}>Resolve</button>}</>} /><Pagination value={data.pagination?.[key]} onChange={(page) => setPages((current) => ({ ...current, [key]: page }))} /></Panel>)}
  </section>
}

function UserFinancial({ api, canWrite, canManage }) {
  const [accounts, setAccounts] = useState([])
  const [rows, setRows] = useState([])
  const [editing, setEditing] = useState(null)
  const [accountEditing, setAccountEditing] = useState(null)
  const [period, setPeriod] = useState('month')
  const [filters, setFilters] = useState({ search: '', transaction_type: '', account_id: '', date_from: '', date_to: '' })
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({ page: 1, pages: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const dates = period === 'custom' ? { date_from: filters.date_from, date_to: filters.date_to } : periodDates(period)
  const debouncedSearch = useDebouncedValue(filters.search)
  const query = new URLSearchParams({ ...filters, ...dates, search: debouncedSearch, page: String(page), limit: '25' }).toString()

  const load = useCallback(async () => {
    try {
      setLoading(true); setError('')
      const [accountRows, transactionRows] = await Promise.all([api.request('/financial-accounts'), api.request(`/financial-transactions?${query}`)])
      setAccounts(accountRows)
      setRows(transactionRows.rows || transactionRows)
      setPagination(transactionRows.pagination || { page: 1, pages: 1, total: transactionRows.length })
    } catch (err) { setError(err.message) } finally { setLoading(false) }
  }, [api, query])
  useEffect(() => { load() }, [load])

  async function save(data) {
    if (saving) return
    setSaving(true)
    try {
      await api.request(`/financial-transactions${data.id ? `/${data.id}` : ''}`, { method: data.id ? 'PUT' : 'POST', body: JSON.stringify(data) })
      setEditing(null); await load()
    } catch (err) { setError(err.message) } finally { setSaving(false) }
  }
  async function remove(id) {
    if (!confirm('Delete this financial transaction?')) return
    try { await api.request(`/financial-transactions/${id}`, { method: 'DELETE' }); await load() } catch (err) { setError(err.message) }
  }
  async function saveAccount(data) {
    if (saving) return
    setSaving(true)
    try {
      await api.request(`/financial-accounts${data.id ? `/${data.id}` : ''}`, { method: data.id ? 'PUT' : 'POST', body: JSON.stringify(data) })
      setAccountEditing(null); await load()
    } catch (err) { setError(err.message) } finally { setSaving(false) }
  }
  async function removeAccount(id) {
    if (!confirm('Delete this financial account? Accounts with history must be set to Inactive instead.')) return
    try { await api.request(`/financial-accounts/${id}`, { method: 'DELETE' }); await load() } catch (err) { setError(err.message) }
  }
  const totalBalance = accounts.reduce((sum, account) => sum + Number(account.balance || 0), 0)
  const activeAccountCount = accounts.filter((account) => account.status === 'Active').length
  const setFilter = (name, value) => { setFilters({ ...filters, [name]: value }); setPage(1) }
  const columns = [{ key: 'transaction_date', label: 'Date' }, { key: 'transaction_type', label: 'Movement' }, { key: 'from_account_name', label: 'From / Used By' }, { key: 'to_account_name', label: 'To / Received By' }, { key: 'project_name', label: 'Project' }, { key: 'amount' }]
  const accountColumns = [{ key: 'name' }, { key: 'opening_balance' }, { key: 'balance' }, { key: 'status' }]
  return <section className="page financial-page">
    <div className="toolbar"><h2>User Financial</h2><div className="actions">{canManage && <button onClick={() => { setError(''); setAccountEditing({ name: '', opening_balance: 0, status: 'Active' }) }}><Plus size={16} />Add Account</button>}{canWrite && <><button disabled={activeAccountCount < 1} onClick={() => { setError(''); setEditing({ transaction_type: 'Use', transaction_date: today(), from_account_id: '', to_account_id: '', amount: '', notes: '' }) }}>Use Money</button><button className="primary" disabled={activeAccountCount < 2} onClick={() => { setError(''); setEditing({ transaction_type: 'Transfer', transaction_date: today(), from_account_id: '', to_account_id: '', amount: '', notes: '' }) }}>Transfer</button></>}</div></div>
    <div className="summary-grid financial-summary">{accounts.map((account) => <Card key={account.id} label={account.name} value={currency(account.balance)} />)}<Card label="Combined Balance" value={currency(totalBalance)} /></div>
    <Panel title="Financial Accounts"><PaginatedTable rows={accounts} columns={accountColumns} actions={canManage ? (account) => <><ActionButton label="Edit account" icon={Pencil} onClick={() => { setError(''); setAccountEditing(account) }} /><ActionButton label="Delete account" icon={Trash2} danger onClick={() => removeAccount(account.id)} /></> : null} /></Panel>
    <div className="period-control" role="group" aria-label="Financial period">{[['today','Today'],['week','Week'],['month','Month'],['lifetime','Lifetime']].map(([value, text]) => <button key={value} className={period === value ? 'active' : ''} onClick={() => { setPeriod(value); setPage(1) }}>{text}</button>)}</div>
    <div className="filters"><input type="search" placeholder="Search notes or project" value={filters.search} onChange={(e) => setFilter('search', e.target.value)} /><select value={filters.transaction_type} onChange={(e) => setFilter('transaction_type', e.target.value)}><option value="">All movements</option><option>Receive</option><option>Use</option><option>Transfer</option></select><select value={filters.account_id} onChange={(e) => setFilter('account_id', e.target.value)}><option value="">All accounts</option>{accounts.map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}</select><input aria-label="Date from" type="date" value={dates.date_from} onChange={(e) => { setPeriod('custom'); setFilter('date_from', e.target.value) }} /><input aria-label="Date to" type="date" value={dates.date_to} onChange={(e) => { setPeriod('custom'); setFilter('date_to', e.target.value) }} /></div>
    {error && <div className="alert">{error}</div>}
    {loading ? <Loading label="Loading financial history" /> : <Table rows={rows} columns={columns} actions={(row) => row.transaction_type !== 'Receive' && !row.domain_billing_period_id && canWrite ? <><ActionButton label="Edit transaction" icon={Pencil} onClick={() => setEditing(row)} /><ActionButton label="Delete transaction" icon={Trash2} danger onClick={() => remove(row.id)} /></> : null} />}
    <Pagination value={pagination} onChange={setPage} />
    {editing && <Modal title={editing.id ? `Edit ${editing.transaction_type}` : editing.transaction_type} onClose={() => setEditing(null)}>{error && <div className="modal-alert alert">{error}</div>}<FinancialTransactionForm initial={editing} accounts={accounts} onSubmit={save} submitting={saving} /></Modal>}
    {accountEditing && <Modal title={accountEditing.id ? 'Edit Financial Account' : 'Add Financial Account'} onClose={() => setAccountEditing(null)}>{error && <div className="modal-alert alert">{error}</div>}<FinancialAccountForm initial={accountEditing} onSubmit={saveAccount} submitting={saving} /></Modal>}
  </section>
}

function FinancialAccountForm({ initial, onSubmit, submitting }) {
  const [form, setForm] = useState(initial)
  return <form className="form-grid" onSubmit={(event) => { event.preventDefault(); onSubmit(form) }}>
    <label className="full">Name<input required maxLength="150" value={form.name || ''} onChange={(event) => setForm({ ...form, name: event.target.value })} /></label>
    <label>Opening Balance<input required type="number" min="0" step="0.01" value={form.opening_balance ?? 0} onChange={(event) => setForm({ ...form, opening_balance: event.target.value })} /></label>
    <label>Status<select required value={form.status || 'Active'} onChange={(event) => setForm({ ...form, status: event.target.value })}><option>Active</option><option>Inactive</option></select></label>
    <div className="form-actions"><button className="primary" disabled={submitting}>{submitting ? 'Saving...' : 'Save Account'}</button></div>
  </form>
}

function FinancialTransactionForm({ initial, accounts, onSubmit, submitting }) {
  const [form, setForm] = useState(initial)
  const activeAccounts = accounts.filter((account) => account.status === 'Active' || [initial.from_account_id, initial.to_account_id].map(String).includes(String(account.id)))
  return <form className="form-grid" onSubmit={(event) => { event.preventDefault(); onSubmit(form) }}>
    <label>Date<input required type="date" value={form.transaction_date || ''} onChange={(event) => setForm({ ...form, transaction_date: event.target.value })} /></label>
    <label>{form.transaction_type === 'Use' ? 'Used By' : 'From'}<select required value={form.from_account_id || ''} onChange={(event) => setForm({ ...form, from_account_id: event.target.value })}><option value="">Choose account</option>{activeAccounts.map((account) => <option key={account.id} value={account.id}>{account.name}{account.status === 'Inactive' ? ' (Inactive)' : ''} · {currency(account.balance)}</option>)}</select></label>
    {form.transaction_type === 'Transfer' && <label>To<select required value={form.to_account_id || ''} onChange={(event) => setForm({ ...form, to_account_id: event.target.value })}><option value="">Choose account</option>{activeAccounts.filter((account) => String(account.id) !== String(form.from_account_id)).map((account) => <option key={account.id} value={account.id}>{account.name}{account.status === 'Inactive' ? ' (Inactive)' : ''}</option>)}</select></label>}
    <label>Amount<input required type="number" min="0.01" step="0.01" value={form.amount || ''} onChange={(event) => setForm({ ...form, amount: event.target.value })} /></label>
    <label className="full">Note<input value={form.notes || ''} onChange={(event) => setForm({ ...form, notes: event.target.value })} /></label>
    <div className="form-actions"><button className="primary" disabled={submitting}>{submitting ? 'Saving…' : `Save ${form.transaction_type}`}</button></div>
  </form>
}

function Reports({ api }) {
  const reportKinds = [
    ['financial-overview','Financial Collection Overview'], ['project-financial','Project Financial'], ['payment-collection','Payment Collection'], ['outstanding-balance','Outstanding Balance'], ['expense','Expenses'], ['profit','Profit'], ['recurring-fees','Recurring Fees'], ['domain-billing','Domain Billing'], ['invoice','Invoices'], ['monthly-income-expense','Monthly Income & Expense'],
  ]
  const [kind, setKind] = useState('financial-overview')
  const [period, setPeriod] = useState('month')
  const emptyReportFilters = { project_id: '', financial_account_id: '', fee_type: '', expense_category: '', status: '', payment_status: '', purchase_status: '', payment_method: '', invoice_type: '', date_from: '', date_to: '' }
  const [filters, setFilters] = useState(emptyReportFilters)
  const [projects, setProjects] = useState([])
  const [financialAccounts, setFinancialAccounts] = useState([])
  const [data, setData] = useState({ summary: {}, payments: [], outstanding_projects: [], recurring_fees: [] })
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  useEffect(() => { api.request('/projects?compact=1').then(setProjects).catch((err) => setError(err.message)) }, [api])
  useEffect(() => { api.request('/financial-accounts').then(setFinancialAccounts).catch((err) => setError(err.message)) }, [api])
  useEffect(() => {
    setLoading(true); setError('')
    const paged = !['financial-overview','monthly-income-expense'].includes(kind)
    const query = new URLSearchParams({ ...filters, ...(kind === 'financial-overview' ? { period } : {}), ...(paged ? { page: String(page), limit: '100' } : {}) })
    api.request(`/reports/${kind}?${query}`).then(setData).catch((err) => setError(err.message)).finally(() => setLoading(false))
  }, [api, kind, period, filters, page])

  const overview = kind === 'financial-overview'
  const overviewRows = overview ? [
    ...(data.payments || []).map((row) => ({ record_type: 'Payment received', date: row.payment_date, project: row.project_name, customer: row.customer_company_name, category: Number(row.is_historical) === 1 ? 'Historical' : row.financial_account_name, amount: row.amount, status: '' })),
    ...(data.outstanding_projects || []).map((row) => ({ record_type: 'Project balance', date: row.payment_due_date, project: row.project_name, customer: row.customer_company_name, category: 'Contract balance', amount: row.remaining_balance, status: row.payment_status })),
    ...(data.recurring_fees || []).map((row) => ({ record_type: 'Recurring fee', date: row.next_due_date, project: row.project_name, customer: row.customer_company_name, category: row.fee_type, amount: row.amount, status: row.status })),
    ...(data.domain_billings || []).map((row) => ({ record_type: 'Domain balance', date: row.customer_due_date, project: row.project_name, customer: row.customer_company_name, category: row.domain_name || row.period_label, amount: row.customer_balance_amount, status: row.customer_payment_status })),
  ] : []
  const monthlyRows = kind === 'monthly-income-expense' ? mergeMonthly(data) : []
  const rows = overview ? overviewRows : kind === 'monthly-income-expense' ? monthlyRows : Array.isArray(data) ? data : data.rows || []
  const reportTitle = reportKinds.find(([value]) => value === kind)?.[1] || 'Report'
  const rangeLabel = overview && data.date_from ? `${data.date_from} to ${data.date_to}` : filters.date_from || filters.date_to ? `${filters.date_from || 'Beginning'} to ${filters.date_to || 'Today'}` : 'All dates'
  const setFilter = (name, value) => { setFilters({ ...filters, [name]: value }); setPage(1) }
  return <section className="page reports-page">
    <div className="toolbar"><div><h2>Financial Reports</h2><p className="muted">Review collections, balances, expenses, fees, invoices, and profit.</p></div><div className="actions"><button onClick={() => window.print()}>Print</button><button disabled={!rows.length} onClick={() => exportCsv(rows, `${kind}.csv`)}>{data.pagination ? 'Export Page CSV' : 'Export CSV'}</button></div></div>
    <form className="report-filters" aria-label="Financial report filters" onSubmit={(event) => event.preventDefault()}>
      <div className="report-filter-scroll"><div className="report-filter-fields">
        <label className="report-filter-kind">Report<select value={kind} onChange={(e) => { setKind(e.target.value); setPage(1); setFilters(emptyReportFilters) }}>{reportKinds.map(([value, text]) => <option key={value} value={value}>{text}</option>)}</select></label>
        <div className="report-filter-project"><ProjectPicker api={api} projects={projects} value={filters.project_id} onChange={(value) => setFilter('project_id', value)} allOption /></div>
        {overview && <label className="report-filter-period">Period<span className="period-control" role="group" aria-label="Report period">{[['today','Today'],['week','This Week'],['month','This Month'],['lifetime','Lifetime']].map(([value, text]) => <button type="button" key={value} className={period === value ? 'active' : ''} onClick={() => setPeriod(value)}>{text}</button>)}</span></label>}
        {(overview || kind === 'recurring-fees') && <label className="report-filter-select">Fee type<select value={filters.fee_type} onChange={(e) => setFilter('fee_type', e.target.value)}><option value="">All fee types</option>{feeTypes.map((type) => <option key={type}>{type}</option>)}</select></label>}
        {kind === 'domain-billing' && <><label className="report-filter-select">Payment<select value={filters.payment_status} onChange={(e) => setFilter('payment_status', e.target.value)}><option value="">All payment states</option>{['Not Priced','Unpaid','Partially Paid','Paid'].map((status) => <option key={status}>{status}</option>)}</select></label><label className="report-filter-select">Purchase<select value={filters.purchase_status} onChange={(e) => setFilter('purchase_status', e.target.value)}><option value="">All purchase states</option>{['Not Purchased','Active','Expired','Cancelled'].map((status) => <option key={status}>{status}</option>)}</select></label></>}
        {kind === 'expense' && <><label className="report-filter-select">Category<select value={filters.expense_category} onChange={(e) => setFilter('expense_category', e.target.value)}><option value="">All expense categories</option>{expenseCategories.map((category) => <option key={category}>{category}</option>)}</select></label><label className="report-filter-select">Method<select value={filters.payment_method} onChange={(e) => setFilter('payment_method', e.target.value)}><option value="">All payment methods</option>{paymentMethods.map((method) => <option key={method}>{method}</option>)}</select></label></>}
        {kind === 'payment-collection' && <label className="report-filter-select">Receiver<select value={filters.financial_account_id} onChange={(e) => setFilter('financial_account_id', e.target.value)}><option value="">All receivers</option>{financialAccounts.map((account) => <option value={account.id} key={account.id}>{account.name}</option>)}</select></label>}
        {kind === 'invoice' && <><label className="report-filter-select">Invoice type<select value={filters.invoice_type} onChange={(e) => setFilter('invoice_type', e.target.value)}><option value="">All invoice types</option>{invoiceTypes.map((type) => <option key={type}>{type}</option>)}</select></label><label className="report-filter-select">Status<select value={filters.status} onChange={(e) => setFilter('status', e.target.value)}><option value="">All invoice statuses</option>{['Draft','Sent','Partially Paid','Paid','Overdue','Cancelled'].map((status) => <option key={status}>{status}</option>)}</select></label></>}
        {kind === 'recurring-fees' && <label className="report-filter-select">Status<select value={filters.status} onChange={(e) => setFilter('status', e.target.value)}><option value="">All fee statuses</option>{['Not Due','Due Soon','Due Today','Overdue','Paid','Cancelled'].map((status) => <option key={status}>{status}</option>)}</select></label>}
        {['project-financial','outstanding-balance','profit'].includes(kind) && <><label className="report-filter-select">Project status<select value={filters.status} onChange={(e) => setFilter('status', e.target.value)}><option value="">All project statuses</option>{projectStatuses.map((status) => <option key={status}>{status}</option>)}</select></label><label className="report-filter-select">Payment status<select value={filters.payment_status} onChange={(e) => setFilter('payment_status', e.target.value)}><option value="">All payment statuses</option>{paymentStatuses.map((status) => <option key={status}>{status}</option>)}</select></label></>}
        {!overview && <><label className="report-filter-date">From<input type="date" value={filters.date_from} onChange={(e) => setFilter('date_from', e.target.value)} /></label><label className="report-filter-date">To<input type="date" value={filters.date_to} onChange={(e) => setFilter('date_to', e.target.value)} /></label></>}
        {overview && data.date_from && <label className="report-filter-range">Date range<output>{data.date_from} to {data.date_to}</output></label>}
      </div></div>
      <div className="report-filter-actions"><button type="button" onClick={() => { setFilters(emptyReportFilters); setPeriod('month'); setPage(1) }}>Clear</button></div>
    </form>
    {error && <div className="alert">{error}</div>}
    {loading ? <Loading label="Loading report" /> : overview ? <>
      <div className="summary-grid report-summary"><Card label="Amount Received" value={currency(data.summary?.received_amount)} /><Card label="Project Balance to Get" value={currency(data.summary?.project_outstanding_amount)} /><Card label="Domain Balance to Get" value={currency(data.summary?.domain_outstanding_amount)} /><Card label="Renewal Fees Due" value={currency(data.summary?.recurring_due_amount)} /><Card label="Total to Get" value={currency(data.summary?.total_to_collect)} /><Card label="Quoted Domain Price" value={currency(data.summary?.domain_server_price_total)} /><Card label="Registrar Cost" value={currency(data.summary?.domain_registrar_cost_total)} /></div>
      <Panel title="Payments Received"><PaginatedTable rows={data.payments || []} columns={[{ key: 'payment_date' }, { key: 'project_name' }, { key: 'customer_company_name', label: 'Customer' }, { key: 'is_historical', label: 'Source' }, { key: 'financial_account_name', label: 'Received By' }, { key: 'amount' }]} /></Panel>
      <Panel title="Project Balances to Collect"><PaginatedTable rows={data.outstanding_projects || []} columns={[{ key: 'project_code' }, { key: 'project_name' }, { key: 'contact_person', label: 'Owner' }, { key: 'contact_phone' }, { key: 'payment_due_date' }, { key: 'remaining_balance' }, { key: 'payment_status' }]} /></Panel>
      <Panel title="Domain Balances to Collect"><PaginatedTable rows={data.domain_billings || []} columns={[{ key: 'project_name' }, { key: 'domain_name', label: 'Domain' }, { key: 'period_label', label: 'Period' }, { key: 'customer_due_date' }, { key: 'customer_price' }, { key: 'customer_paid_amount', label: 'Paid' }, { key: 'customer_balance_amount', label: 'Balance' }, { key: 'customer_payment_status', label: 'Status' }]} /></Panel>
      <Panel title="Domain, Server & Recurring Fees"><PaginatedTable rows={data.recurring_fees || []} columns={[{ key: 'project_name' }, { key: 'fee_name' }, { key: 'fee_type' }, { key: 'amount' }, { key: 'next_due_date' }, { key: 'status' }]} /></Panel>
    </> : <><Panel title={`${reportTitle} - ${rangeLabel}`}>{kind === 'monthly-income-expense' && <Suspense fallback={<Loading label="Loading chart" />}><MonthlyReportChart rows={monthlyRows} formatCurrency={currency} /></Suspense>}<Table rows={rows} columns={reportColumns(kind, rows)} /></Panel><Pagination value={data.pagination} onChange={setPage} /></>}
  </section>
}

function mergeMonthly(data) {
  const map = new Map()
  ;(data?.income || []).forEach((row) => map.set(row.month, { month: row.month, income: Number(row.total), expenses: 0 }))
  ;(data?.expenses || []).forEach((row) => map.set(row.month, { ...(map.get(row.month) || { month: row.month, income: 0 }), expenses: Number(row.total) }))
  return [...map.values()].sort((a, b) => a.month.localeCompare(b.month))
}

function reportColumns(kind, rows) {
  const columns = {
    'project-financial': [{ key: 'project_code' }, { key: 'project_name' }, { key: 'customer_company_name' }, { key: 'total_payable' }, { key: 'total_paid' }, { key: 'remaining_balance' }, { key: 'total_expenses' }, { key: 'profit' }, { key: 'payment_status' }],
    'outstanding-balance': [{ key: 'project_code' }, { key: 'project_name' }, { key: 'customer_company_name' }, { key: 'contact_person' }, { key: 'contact_phone' }, { key: 'payment_due_date' }, { key: 'remaining_balance' }, { key: 'payment_status' }],
    profit: [{ key: 'project_code' }, { key: 'project_name' }, { key: 'total_payable' }, { key: 'total_paid' }, { key: 'total_expenses' }, { key: 'profit' }],
    'payment-collection': paymentColumns,
    expense: expenseColumns,
    'recurring-fees': feeColumns,
    'domain-billing': [{ key: 'project_name' }, { key: 'domain_name', label: 'Domain' }, { key: 'period_label', label: 'Period' }, { key: 'customer_due_date' }, { key: 'customer_renewal_date' }, { key: 'coverage_end_date', label: 'Registrar Expiry' }, { key: 'customer_price' }, { key: 'customer_paid_amount', label: 'Paid' }, { key: 'customer_balance_amount', label: 'Balance' }, { key: 'actual_registrar_cost' }, { key: 'realized_domain_profit' }, { key: 'customer_payment_status', label: 'Payment' }, { key: 'effective_purchase_status', label: 'Purchase' }],
    invoice: invoiceColumns,
    'monthly-income-expense': [{ key: 'month' }, { key: 'income' }, { key: 'expenses' }],
  }
  return columns[kind] || Object.keys(rows[0] || {}).slice(0, 9).map((key) => ({ key }))
}

function Users({ api, canManage }) {
  const fields = [{ name: 'name' }, { name: 'email' }, { name: 'password', type: 'password' }, { name: 'role', default: 'Staff' }, { name: 'status', default: 'Active' }]
  if (!canManage) return <section className="page"><Panel title="Users"><p>Admin role required.</p></Panel></section>
  return <GenericModule api={api} title="Users" endpoint="/users" fields={fields} columns={[{ key: 'name' }, { key: 'email' }, { key: 'role' }, { key: 'status' }]} options={{ role: ['Admin', 'Staff', 'Viewer'], status: ['Active', 'Inactive'] }} canWrite />
}

function Settings({ api, canManage }) {
  const [rows, setRows] = useState([])
  const [form, setForm] = useState({})
  const [designing, setDesigning] = useState(false)
  const [design, setDesign] = useState(() => invoiceDesignFrom())
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  useEffect(() => { api.request('/settings').then((data) => { const values = Object.fromEntries(data.map((x) => [x.setting_key, x.setting_value])); setRows(data.filter((row) => row.setting_key !== 'invoice_design')); setForm(values); setDesign(invoiceDesignFrom(values.invoice_design)) }).catch((err) => setError(err.message)) }, [api])
  async function save(e) {
    e.preventDefault()
    setMessage(''); setError('')
    try { await api.request('/settings', { method: 'POST', body: JSON.stringify(form) }); setMessage('Settings saved.') } catch (err) { setError(err.message) }
  }
  async function saveDesign(nextDesign) {
    setMessage(''); setError('')
    const invoiceDesign = JSON.stringify(nextDesign)
    try {
      await api.request('/settings', { method: 'POST', body: JSON.stringify({ invoice_design: invoiceDesign }) })
      setDesign(nextDesign); setForm({ ...form, invoice_design: invoiceDesign }); setMessage('Invoice design saved.'); setDesigning(false)
    } catch (err) { setError(err.message); throw err }
  }
  return <section className="page settings-page">
    {error && <div className="alert">{error}</div>}{message && <div className="success-message">{message}</div>}
    <Panel title="Company Settings"><form className="form-grid" onSubmit={save}>{rows.map((row) => <label key={row.setting_key}>{label(row.setting_key)}<input value={form[row.setting_key] || ''} disabled={!canManage} onChange={(e) => setForm({ ...form, [row.setting_key]: e.target.value })} /></label>)}{canManage && <div className="form-actions"><button className="primary">Save Settings</button></div>}</form></Panel>
    <section className="settings-band"><div><h2>Invoice Template</h2><p className="muted">Customize the A4 invoice used by previews and PDF softcopies.</p></div>{canManage && <button className="primary" onClick={() => setDesigning(true)}>Open Invoice Designer</button>}</section>
    {designing && <InvoiceDesigner initial={design} settings={form} onSave={saveDesign} onClose={() => setDesigning(false)} />}
  </section>
}

function InvoiceDesigner({ initial, settings, onSave, onClose }) {
  const [design, setDesign] = useState(() => structuredClone(initial))
  const [selected, setSelected] = useState('header')
  const [saving, setSaving] = useState(false)
  const sampleInvoice = { invoice_number: 'INV-2026-0001', invoice_date: today(), due_date: today(), subtotal: 1500000, discount_amount: 50000, tax_amount: 0, total_amount: 1450000, paid_amount: 400000, balance_amount: 1050000, notes: 'Payment is due by the date shown above.', items: [{ description: 'Website design and development', quantity: 1, unit_price: 1200000 }, { description: 'Domain and server setup', quantity: 1, unit_price: 300000 }] }
  const sampleProject = { customer_company_name: 'Sample Customer', contact_person: 'Project Owner', contact_phone: '09 000 000 000', project_name: 'Sample Project', currency: settings.currency || 'MMK' }
  const updatePage = (key, value) => setDesign({ ...design, page: { ...design.page, [key]: value } })
  const updateTable = (key, value) => setDesign({ ...design, table: { ...design.table, [key]: value } })
  const updateBlock = (id, values) => setDesign({ ...design, blocks: { ...design.blocks, [id]: { ...design.blocks[id], ...values } } })
  const activeBlock = selected ? design.blocks[selected] : null
  async function save() { if (saving) return; setSaving(true); try { await onSave(design) } finally { setSaving(false) } }
  return <FullScreenWorkspace title="Invoice Template Designer" onClose={onClose} actions={<><button onClick={() => { if (confirm('Reset the invoice template to its default layout?')) { setDesign(structuredClone(defaultInvoiceDesign)); setSelected('header') } }}>Reset</button><button className="primary" disabled={saving} onClick={save}>{saving ? 'Saving...' : 'Save Template'}</button></>}>
    <div className="invoice-designer">
      <aside className="designer-components"><h3>Components</h3>{Object.entries(invoiceBlockNames).map(([id, name]) => <div className={`component-row${selected === id ? ' active' : ''}`} key={id}><button onClick={() => setSelected(id)}>{name}</button><label title={`Show ${name}`}><input type="checkbox" checked={design.blocks[id].visible} onChange={(event) => updateBlock(id, { visible: event.target.checked })} /></label></div>)}</aside>
      <main className="designer-stage" onClick={() => setSelected(null)}><InvoiceA4 invoice={sampleInvoice} project={sampleProject} settings={settings} design={design} designer={{ selected, onSelect: setSelected, onChange: updateBlock }} /></main>
      <aside className="designer-inspector">
        <h3>Page</h3>
        <label>Font Family<select value={design.page.fontFamily} onChange={(event) => updatePage('fontFamily', event.target.value)}>{invoiceFontFamilies.map((font) => <option key={font} value={font}>{font.split(',')[0]}</option>)}</select></label>
        <div className="control-grid"><NumberControl label="Base Size" value={design.page.fontSize} min="8" max="24" onChange={(value) => updatePage('fontSize', value)} /><NumberControl label="Line Height" value={design.page.lineHeight} min="0.8" max="3" step="0.05" onChange={(value) => updatePage('lineHeight', value)} /></div>
        <div className="control-grid"><ColorControl label="Text" value={design.page.color} onChange={(value) => updatePage('color', value)} /><ColorControl label="Background" value={design.page.background} onChange={(value) => updatePage('background', value)} /><ColorControl label="Accent" value={design.page.accent} onChange={(value) => updatePage('accent', value)} /></div>
        {activeBlock && <>
          <h3>{invoiceBlockNames[selected]}</h3>
          <label className="check-row"><input type="checkbox" checked={activeBlock.visible} onChange={(event) => updateBlock(selected, { visible: event.target.checked })} />Visible</label>
          <div className="control-grid"><NumberControl label="X %" value={activeBlock.x} min="0" max="100" onChange={(value) => updateBlock(selected, { x: value })} /><NumberControl label="Y %" value={activeBlock.y} min="0" max="100" onChange={(value) => updateBlock(selected, { y: value })} /><NumberControl label="Width %" value={activeBlock.width} min="5" max="100" onChange={(value) => updateBlock(selected, { width: value })} /><NumberControl label="Min Height" value={activeBlock.minHeight || 0} min="0" max="600" onChange={(value) => updateBlock(selected, { minHeight: value })} /></div>
          <div className="control-grid"><NumberControl label="Padding" value={activeBlock.padding} min="0" max="80" onChange={(value) => updateBlock(selected, { padding: value })} /><NumberControl label="Margin" value={activeBlock.margin} min="0" max="80" onChange={(value) => updateBlock(selected, { margin: value })} /></div>
          <label>Text Align<select value={activeBlock.textAlign} onChange={(event) => updateBlock(selected, { textAlign: event.target.value })}><option>left</option><option>center</option><option>right</option></select></label>
          <label>Font Family<select value={activeBlock.fontFamily} onChange={(event) => updateBlock(selected, { fontFamily: event.target.value })}>{invoiceFontFamilies.map((font) => <option key={font} value={font}>{font.split(',')[0]}</option>)}</select></label>
          <div className="control-grid"><NumberControl label="Font Size" value={activeBlock.fontSize} min="6" max="48" onChange={(value) => updateBlock(selected, { fontSize: value })} /><NumberControl label="Weight" value={activeBlock.fontWeight} min="100" max="900" step="100" onChange={(value) => updateBlock(selected, { fontWeight: value })} /><NumberControl label="Line Height" value={activeBlock.lineHeight} min="0.8" max="3" step="0.05" onChange={(value) => updateBlock(selected, { lineHeight: value })} /></div>
          <div className="control-grid"><ColorControl label="Text" value={activeBlock.color} onChange={(value) => updateBlock(selected, { color: value })} /><ColorControl label="Background" value={activeBlock.background} transparent onChange={(value) => updateBlock(selected, { background: value })} /></div>
          {selected === 'table' && <TableDesignControls table={design.table} update={updateTable} />}
        </>}
      </aside>
    </div>
  </FullScreenWorkspace>
}

function NumberControl({ label: text, value, onChange, min, max, step = '0.1' }) {
  return <label>{text}<input type="number" value={value} min={min} max={max} step={step} onChange={(event) => onChange(Number(event.target.value))} /></label>
}

function ColorControl({ label: text, value, onChange, transparent = false }) {
  const color = /^#[0-9a-f]{6}$/i.test(value) ? value : '#ffffff'
  return <label>{text}<span className="color-control"><input type="color" value={color} onChange={(event) => onChange(event.target.value)} />{transparent && <input aria-label="Transparent" title="Transparent" type="checkbox" checked={value === 'transparent'} onChange={(event) => onChange(event.target.checked ? 'transparent' : color)} />}</span></label>
}

function TableDesignControls({ table, update }) {
  return <section className="table-design-controls"><h3>Table Settings</h3><div className="control-grid"><ColorControl label="Header" value={table.headerBackground} onChange={(value) => update('headerBackground', value)} /><ColorControl label="Header Text" value={table.headerColor} onChange={(value) => update('headerColor', value)} /><ColorControl label="Borders" value={table.borderColor} onChange={(value) => update('borderColor', value)} /></div><div className="control-grid"><NumberControl label="Border" value={table.borderWidth} min="0" max="6" onChange={(value) => update('borderWidth', value)} /><NumberControl label="Cell Padding" value={table.cellPadding} min="0" max="30" onChange={(value) => update('cellPadding', value)} /><NumberControl label="Font Size" value={table.fontSize} min="6" max="24" onChange={(value) => update('fontSize', value)} /></div><label>Column Widths (%)<input value={table.columnWidths} onChange={(event) => update('columnWidths', event.target.value)} placeholder="10,61,9,20" /></label><label className="check-row"><input type="checkbox" checked={table.striped} onChange={(event) => update('striped', event.target.checked)} />Striped Rows</label></section>
}

function fieldControl(name, value, onChange, options, type, required = false) {
  const nice = label(name)
  if (type === 'checkbox') return <label key={name} className="check-row"><input type="checkbox" checked={String(value) === '1' || value === true} onChange={(e) => onChange(e.target.checked ? 1 : 0)} />{nice}</label>
  if (options) return <label key={name}>{nice}<select required={required} value={value || ''} onChange={(e) => onChange(e.target.value)}><option value="">Choose</option>{options.map((x) => <option key={x}>{x}</option>)}</select></label>
  if (name.includes('notes') || name.includes('description') || name.includes('address')) return <label key={name} className="full">{nice}<textarea required={required} value={value || ''} onChange={(e) => onChange(e.target.value)} /></label>
  const inputType = type || (name.includes('date') ? 'date' : name.includes('amount') || name.includes('price') || name === 'quantity' || name === 'unit_price' || name === 'reminder_days_before_due' ? 'number' : name.includes('email') ? 'email' : name.includes('phone') ? 'tel' : 'text')
  const step = name === 'reminder_days_before_due' ? '1' : '0.01'
  return <label key={name}>{nice}<input required={required} type={inputType} min={inputType === 'number' ? '0' : undefined} step={inputType === 'number' ? step : undefined} value={value || ''} onChange={(e) => onChange(e.target.value)} /></label>
}

function ProjectPicker({ api, projects = [], value, onChange, required = false, label: pickerLabel = 'Project', allOption = false }) {
  const [search, setSearch] = useState('')
  const [options, setOptions] = useState(projects)
  const debouncedSearch = useDebouncedValue(search)
  useEffect(() => { if (!debouncedSearch.trim()) setOptions(projects) }, [projects, debouncedSearch])
  useEffect(() => {
    if (!api) return undefined
    let active = true
    const query = new URLSearchParams({ compact: '1', limit: '50' })
    if (debouncedSearch.trim()) query.set('search', debouncedSearch.trim())
    if (value) query.set('selected_id', value)
    api.request(`/projects?${query}`).then((rows) => {
      if (!active) return
      const combined = debouncedSearch.trim() ? rows : [...rows, ...projects]
      setOptions(combined.filter((project, index) => combined.findIndex((item) => String(item.id) === String(project.id)) === index))
    }).catch(() => {})
    return () => { active = false }
  }, [api, debouncedSearch, projects, value])
  const selected = [...options, ...projects].find((project) => String(project.id) === String(value))
  const visible = [...options]
  if (selected && !visible.some((project) => String(project.id) === String(selected.id))) visible.unshift(selected)
  return <label>{pickerLabel}<span className="project-picker"><input type="search" aria-label={`Search ${pickerLabel.toLowerCase()}`} placeholder="Search code, name or customer" value={search} onChange={(event) => setSearch(event.target.value)} /><select required={required} value={value || ''} onChange={(event) => { const nextValue = event.target.value; onChange(nextValue, visible.find((project) => String(project.id) === nextValue)) }}><option value="">{allOption ? 'All projects' : 'Choose project'}</option>{visible.map((project) => <option key={project.id} value={project.id}>{project.project_code} - {project.project_name}</option>)}</select></span></label>
}

function Toolbar({ title, canWrite, onAdd }) {
  return <div className="toolbar"><h2>{title}</h2>{canWrite && <button className="primary add-button" onClick={onAdd}><Plus size={16} />Add</button>}</div>
}

function ActionButton({ label: text, icon: Icon, danger = false, ...props }) {
  return <button className={`action-btn${danger ? ' danger' : ''}`} aria-label={text} title={text} {...props}><Icon size={15} /></button>
}

function Table({ rows, columns, actions }) {
  if (!rows?.length) return <Empty text="No records found." />
  return <div className="table-wrap"><table className="data-table"><thead><tr>{columns.map((col) => <th key={col.key}>{col.label || label(col.key)}</th>)}{actions && <th className="action-column">Actions</th>}</tr></thead><tbody>{rows.map((row) => <tr key={`${row.id}-${row.invoice_number || row.receipt_number || row.project_code || ''}`}>{columns.map((col) => <td key={col.key} data-label={col.label || label(col.key)}>{renderCell(row[col.key], col.key)}</td>)}{actions && <td className="action-column" data-label="Actions"><div className="row-actions">{actions(row)}</div></td>}</tr>)}</tbody></table></div>
}

function PaginatedTable({ rows = [], columns, actions, pageSize = 10 }) {
  const [page, setPage] = useState(1)
  const pages = Math.max(1, Math.ceil(rows.length / pageSize))
  useEffect(() => { setPage((current) => Math.min(current, pages)) }, [pages])
  const visibleRows = rows.slice((page - 1) * pageSize, page * pageSize)
  return <><Table rows={visibleRows} columns={columns} actions={actions} /><Pagination value={{ page, pages, total: rows.length, limit: pageSize }} onChange={setPage} /></>
}

function renderCell(value, key) {
  if (key === 'is_historical') return Number(value) === 1 ? 'Historical' : 'Account'
  if (key.includes('amount') || key.includes('paid') || key.includes('balance') || key.includes('total') || ['profit','income','expenses'].includes(key)) return currency(value)
  if (key.includes('status')) return <span className={`badge ${String(value).toLowerCase().replaceAll(' ', '-')}`}>{value}</span>
  return value || '-'
}

function Card({ label, value }) {
  return <div className="card"><span>{label}</span><strong>{value}</strong></div>
}

function Panel({ title, children }) {
  return <section className="panel"><h2>{title}</h2>{children}</section>
}

function MiniList({ rows, main, sub, amount, date }) {
  if (!rows?.length) return <div className="empty small">No data yet.</div>
  return <ul className="mini-list">{rows.map((row, index) => <li key={row.id || index}><div><strong>{row[main]}</strong><span>{row[sub]} {date && `• ${row[date] || ''}`}</span></div>{amount && <b>{currency(row[amount])}</b>}</li>)}</ul>
}

function Modal({ title, children, onClose, wide }) {
  const modalRef = useRef(null)
  useEffect(() => {
    const previous = document.activeElement
    const oldOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    const close = (event) => { if (event.key === 'Escape') onClose() }
    window.addEventListener('keydown', close)
    requestAnimationFrame(() => modalRef.current?.querySelector('input:not([readonly]), select, textarea, button')?.focus())
    return () => { window.removeEventListener('keydown', close); document.body.style.overflow = oldOverflow; previous?.focus?.() }
  }, [onClose])
  return <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}><div ref={modalRef} className={`modal ${wide ? 'wide' : ''}`} role="dialog" aria-modal="true" aria-label={title}><header><h2>{title}</h2><button className="icon-btn" aria-label="Close" onClick={onClose}><X size={19} /></button></header><div className="modal-body">{children}</div></div></div>
}

function FullScreenWorkspace({ title, children, onClose, actions }) {
  useEffect(() => {
    const oldOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    const close = (event) => { if (event.key === 'Escape') onClose() }
    window.addEventListener('keydown', close)
    return () => { window.removeEventListener('keydown', close); document.body.style.overflow = oldOverflow }
  }, [onClose])
  return <div className="fullscreen-workspace" role="dialog" aria-modal="true" aria-label={title}>
    <header className="fullscreen-header"><div><h2>{title}</h2></div><div className="actions">{actions}<button className="icon-btn" aria-label="Close" title="Close" onClick={onClose}><X size={20} /></button></div></header>
    <div className="fullscreen-body">{children}</div>
  </div>
}

function Loading({ label: text = 'Loading' }) {
  return <div className="loading" role="status"><LoaderCircle size={20} className="spin" />{text}</div>
}

function Empty({ text }) {
  return <div className="empty">{text}</div>
}

function Pagination({ value, onChange }) {
  if (!value || value.pages <= 1) return null
  return <div className="pagination"><button aria-label="First page" title="First page" disabled={value.page <= 1} onClick={() => onChange(1)}><ChevronsLeft size={16} /></button><button aria-label="Previous page" title="Previous page" disabled={value.page <= 1} onClick={() => onChange(value.page - 1)}><ChevronLeft size={16} /></button><span>Page {value.page} of {value.pages} · {value.total} records</span><button aria-label="Next page" title="Next page" disabled={value.page >= value.pages} onClick={() => onChange(value.page + 1)}><ChevronRight size={16} /></button><button aria-label="Last page" title="Last page" disabled={value.page >= value.pages} onClick={() => onChange(value.pages)}><ChevronsRight size={16} /></button></div>
}

function label(key) {
  const overrides = { coverage_start_date: 'Registrar Coverage Start', coverage_end_date: 'Registrar Expiry Date', renewal_reminder_date: 'Registrar Reminder Date', customer_renewal_date: 'Customer Renewal Date', is_registrar_carryover: 'Registrar Coverage Carried Forward' }
  return overrides[key] || String(key).replaceAll('_', ' ').replace(/\b\w/g, (m) => m.toUpperCase())
}

function exportCsv(rows, filename = 'report.csv') {
  if (!rows.length) return
  const csv = [Object.keys(rows[0] || {}).join(','), ...rows.map((row) => Object.values(row).map((x) => `"${String(x ?? '').replaceAll('"', '""')}"`).join(','))].join('\n')
  const link = document.createElement('a')
  link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }))
  link.download = filename
  link.click()
  URL.revokeObjectURL(link.href)
}

const projectColumns = [{ key: 'project_code' }, { key: 'project_name' }, { key: 'customer_company_name', label: 'Customer' }, { key: 'contact_phone' }, { key: 'status' }, { key: 'contract_amount' }, { key: 'total_paid', label: 'Paid' }, { key: 'remaining_balance', label: 'Balance' }, { key: 'payment_status' }, { key: 'delivery_date' }]
const paymentColumns = [{ key: 'payment_date' }, { key: 'project_code' }, { key: 'project_name' }, { key: 'customer_company_name', label: 'Customer' }, { key: 'payment_type', label: 'Stage' }, { key: 'payment_scope', label: 'For' }, { key: 'is_historical', label: 'Source' }, { key: 'domain_name', label: 'Domain' }, { key: 'amount' }, { key: 'financial_account_name', label: 'Received By' }, { key: 'recorded_by_name', label: 'Recorded By' }]
const feeColumns = [{ key: 'project_name' }, { key: 'fee_name' }, { key: 'fee_type' }, { key: 'source_type', label: 'Source' }, { key: 'amount' }, { key: 'billing_cycle' }, { key: 'next_due_date' }, { key: 'status' }]
const expenseColumns = [{ key: 'expense_date' }, { key: 'project_name' }, { key: 'expense_category' }, { key: 'domain_name', label: 'Domain' }, { key: 'amount' }, { key: 'paid_to' }, { key: 'payment_method' }, { key: 'created_by_name', label: 'Created By' }]
const invoiceColumns = [{ key: 'invoice_number' }, { key: 'invoice_date' }, { key: 'project_name' }, { key: 'customer_company_name' }, { key: 'total_amount' }, { key: 'paid_amount' }, { key: 'balance_amount' }, { key: 'status' }]
const receiptColumns = [{ key: 'receipt_number' }, { key: 'receipt_date' }, { key: 'project_name' }, { key: 'amount' }, { key: 'payment_method' }, { key: 'received_from' }, { key: 'received_by_name', label: 'Recorded By' }]

const paymentFields = [{ name: 'project_id' }, { name: 'payment_date', default: today() }, { name: 'amount' }, { name: 'payment_type', label: 'Payment Stage', default: 'Upfront' }, { name: 'is_historical', label: 'Historical Payment', default: 0, type: 'checkbox' }, { name: 'financial_account_id' }, { name: 'reference_number' }, { name: 'notes' }, { name: 'payment_method', default: 'Cash', hidden: true }]
const feeFields = [{ name: 'project_id' }, { name: 'fee_name', default: '' }, { name: 'fee_type', default: 'Hosting' }, { name: 'amount' }, { name: 'billing_cycle', default: 'Yearly' }, { name: 'next_due_date' }, { name: 'reminder_days_before_due', default: 7 }, { name: 'auto_create_reminder', default: 1, type: 'checkbox' }, { name: 'notes' }, { name: 'status', default: 'Not Due', hidden: true }]
const expenseFields = [{ name: 'project_id' }, { name: 'expense_date', default: today() }, { name: 'expense_category', default: 'Other' }, { name: 'amount' }, { name: 'paid_to' }, { name: 'payment_method', default: 'Cash' }, { name: 'reference_number' }, { name: 'notes' }]
const receiptFields = [{ name: 'project_id' }, { name: 'payment_id' }, { name: 'receipt_date', default: today() }, { name: 'amount' }, { name: 'payment_method', default: 'Cash', hidden: true }, { name: 'received_from' }, { name: 'notes' }]

export default App
