import { Bar, BarChart, CartesianGrid, Cell, Legend, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'

const pieColors = ['#176b87', '#e0a321', '#16835c', '#b42318']

export function DashboardCharts({ monthly, paymentPie, recurringDue, formatCurrency }) {
  return <div className="dashboard-charts">
    <ChartPanel title="Income and Expenses">{monthly.length ? <ResponsiveContainer width="100%" height="100%"><LineChart data={monthly}><CartesianGrid strokeDasharray="3 3" /><XAxis dataKey="month" /><YAxis width={62} /><Tooltip formatter={(value) => formatCurrency(value)} /><Legend /><Line type="monotone" dataKey="income" stroke="#16835c" strokeWidth={2} /><Line type="monotone" dataKey="expenses" stroke="#b42318" strokeWidth={2} /></LineChart></ResponsiveContainer> : <ChartEmpty text="No monthly finance data yet." />}</ChartPanel>
    <ChartPanel title="Project Payment Status">{paymentPie.length ? <ResponsiveContainer width="100%" height="100%"><PieChart><Pie data={paymentPie} dataKey="value" nameKey="name" innerRadius={48} outerRadius={82} paddingAngle={2}>{paymentPie.map((entry, index) => <Cell key={entry.name} fill={pieColors[index % pieColors.length]} />)}</Pie><Tooltip /><Legend /></PieChart></ResponsiveContainer> : <ChartEmpty text="No project status data yet." />}</ChartPanel>
    <ChartPanel title="Recurring Fees by Type">{recurringDue.length ? <ResponsiveContainer width="100%" height="100%"><BarChart data={recurringDue}><CartesianGrid strokeDasharray="3 3" /><XAxis dataKey="fee_type" /><YAxis allowDecimals={false} /><Tooltip /><Bar dataKey="total" fill="#176b87" /></BarChart></ResponsiveContainer> : <ChartEmpty text="No recurring fees yet." />}</ChartPanel>
  </div>
}

export function MonthlyReportChart({ rows, formatCurrency }) {
  if (!rows.length) return null
  return <div className="chart-box report-chart"><ResponsiveContainer width="100%" height="100%"><BarChart data={rows}><CartesianGrid strokeDasharray="3 3" /><XAxis dataKey="month" /><YAxis width={62} /><Tooltip formatter={(value) => formatCurrency(value)} /><Legend /><Bar dataKey="income" fill="#16835c" /><Bar dataKey="expenses" fill="#b42318" /></BarChart></ResponsiveContainer></div>
}

function ChartPanel({ title, children }) {
  return <section className="panel"><h2>{title}</h2><div className="chart-box">{children}</div></section>
}

function ChartEmpty({ text }) {
  return <div className="empty">{text}</div>
}
