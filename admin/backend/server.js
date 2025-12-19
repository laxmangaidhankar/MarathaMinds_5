const express = require('express');
const cors = require('cors');
const path = require('path');

const app = express();
app.use(cors());
app.use(express.json()); // ✅ JSON middleware

/* =======================
   SERVE STATIC FILES
======================= */
// Serve HTML files from ../html relative to backend/server.js
app.use(express.static(path.join(__dirname, '../html')));

/* =======================
   DASHBOARD DATA (ADMIN)
======================= */
let dashboardData = {
  workersActiveToday: 196,
  totalWorkDoneToday: 47,
  workersOnLeave: 14,
  operationalImpactZones: 3,
  complaintsToday: 26,
  dailyReports: 109,
  attendanceTracked: "92%",
  tasksAssigned: 0, // start from 0
  recentActivities: [
    { type: "Pickup Completed", zone: "Zone A", requests: 12, status: "Completed" },
    { type: "Pickup In Progress", zone: "Zone C", requests: 5, status: "Ongoing" },
    { type: "New User Registered", zone: "Kothrud Area", time: "2 min ago" },
    { type: "Zone Added", zone: "Baner", time: "Today" },
    { type: "Pickup Failed", zone: "Zone D", reason: "Vehicle Issue", status: "Alert" }
  ]
};

/* =======================
   REPORTS DATA
======================= */
let reportsData = {
  totalWorkers: 48,
  insideApproved: 35,
  outsideArea: 8,
  inactiveWorkers: 5
};

/* =======================
   TASK PLANNING DATA
======================= */
// Workers list
let workers = [
  { id: 1, name: "Ramesh Patil" },
  { id: 2, name: "Suresh Kale" },
  { id: 3, name: "Sunita Pawar" }
];

// Assigned tasks
let tasks = [];

/* =======================
   API ROUTES
======================= */

// 🔹 Dashboard stats
app.get('/api/dashboard-stats', (req, res) => {
  res.json(dashboardData);
});

// 🔹 Update dashboard stats
app.post('/api/update-stats', (req, res) => {
  dashboardData = { ...dashboardData, ...req.body };
  res.json({ message: 'Dashboard updated', dashboardData });
});

// 🔹 Reports data
app.get('/api/reports', (req, res) => {
  res.json(reportsData);
});

// 🔹 Update reports
app.post('/api/reports', (req, res) => {
  reportsData = { ...reportsData, ...req.body };
  res.json({ message: 'Reports updated', reportsData });
});

/* =======================
   TASK PLANNING APIs
======================= */

// ✅ Get workers (dropdown)
app.get('/api/workers', (req, res) => {
  res.json(workers);
});

// ✅ Assign task
app.post('/api/assign-task', (req, res) => {
  const { workerId, task } = req.body;

  if (!workerId || !task) {
    return res.status(400).json({ message: "Worker and task are required" });
  }

  const worker = workers.find(w => w.id == workerId);
  if (!worker) {
    return res.status(404).json({ message: "Worker not found" });
  }

  const newTask = {
    id: tasks.length + 1,
    workerId: worker.id,
    workerName: worker.name,
    task,
    status: "Assigned",
    date: new Date().toLocaleString()
  };

  tasks.push(newTask);

  // Update dashboard task count
  dashboardData.tasksAssigned = tasks.length;

  // Add recent activity
  dashboardData.recentActivities.unshift({
    type: "Task Assigned",
    zone: worker.name,
    time: "Just now"
  });

  res.json({ message: "Task assigned successfully", task: newTask });
});

// ✅ Get all tasks
app.get('/api/tasks', (req, res) => {
  res.json(tasks);
});

// ✅ Update task status
app.post('/api/update-task-status', (req, res) => {
  const { taskId, status } = req.body;

  const task = tasks.find(t => t.id == taskId);
  if (!task) {
    return res.status(404).json({ message: "Task not found" });
  }

  task.status = status;
  res.json({ message: "Task status updated", task });
});

/* =======================
   SERVER START
======================= */
const PORT = 3000;
app.listen(PORT, () => {
  console.log(`✅ Server running on http://localhost:${PORT}`);
});
