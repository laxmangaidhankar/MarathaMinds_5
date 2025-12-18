// Sample dynamic data
const data = {
  activeWorkers: [50, 60, 70, 65, 80, 75, 90],
  workersLeave: [5, 3, 8, 6, 4, 7, 2],
  workerShortage: [10, 15, 5, 8, 12, 6, 10],
  tasksToday: [100, 120, 110, 95, 130, 125, 140]
};

// Update cards with latest values
document.getElementById('active-workers').innerText = data.activeWorkers.slice(-1);
document.getElementById('workers-leave').innerText = data.workersLeave.slice(-1);
document.getElementById('worker-shortage').innerText = data.workerShortage.slice(-1) + '%';
document.getElementById('tasks-today').innerText = data.tasksToday.slice(-1);

// Chart Options
function createChart(ctx, dataset, label, color) {
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
      datasets: [{
        label: label,
        data: dataset,
        borderColor: color,
        backgroundColor: 'rgba(0,0,0,0)',
        tension: 0.4,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { x: { display: false }, y: { display: false } }
    }
  });
}

// Create all charts
createChart(document.getElementById('chart1'), data.activeWorkers, 'Active Workers', '#3b82f6');
createChart(document.getElementById('chart2'), data.workersLeave, 'Workers on Leave', '#ef4444');
createChart(document.getElementById('chart3'), data.workerShortage, 'Worker Shortage', '#f59e0b');
createChart(document.getElementById('chart4'), data.tasksToday, 'Tasks Today', '#10b981');
