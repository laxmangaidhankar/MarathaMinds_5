document.addEventListener("DOMContentLoaded", () => {
  const activityList = document.getElementById("recent-activity-list");
  const filterItems = document.querySelectorAll(".dropdown-item");

  // Sample activity data (can be replaced with API later)
  const activities = {
    today: [
      {
        type: "success",
        icon: "bx-check-circle",
        title: "Pickup Completed",
        desc: "Zone A – 12 Requests",
        badge: "Completed"
      },
      {
        type: "primary",
        icon: "bx-user-plus",
        title: "New User Registered",
        desc: "Kothrud Area",
        badge: "2 min ago"
      }
    ],
    week: [
      {
        type: "warning",
        icon: "bx-time",
        title: "Pickup In Progress",
        desc: "Zone C – 5 Requests",
        badge: "Ongoing"
      },
      {
        type: "info",
        icon: "bx-map",
        title: "Zone Added",
        desc: "New Zone: Baner",
        badge: "This Week"
      }
    ],
    month: [
      {
        type: "danger",
        icon: "bx-error-circle",
        title: "Pickup Failed",
        desc: "Zone D – Vehicle Issue",
        badge: "Alert"
      }
    ]
  };

  // Render activities
  function renderActivities(list) {
    activityList.innerHTML = "";

    list.forEach(item => {
      activityList.innerHTML += `
        <li class="d-flex align-items-center mb-6">
          <div class="avatar flex-shrink-0 me-3 bg-label-${item.type} rounded">
            <i class="bx ${item.icon} icon-lg"></i>
          </div>
          <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
            <div class="me-2">
              <small class="d-block text-muted">${item.title}</small>
              <h6 class="fw-normal mb-0">${item.desc}</h6>
            </div>
            <span class="badge bg-label-${item.type}">${item.badge}</span>
          </div>
        </li>
      `;
    });
  }

  // Default load
  renderActivities(activities.today);

  // Dropdown filter handling
  filterItems.forEach(item => {
    item.addEventListener("click", () => {
      const text = item.textContent.toLowerCase();

      if (text.includes("today")) renderActivities(activities.today);
      if (text.includes("week")) renderActivities(activities.week);
      if (text.includes("month")) renderActivities(activities.month);
    });
  });
});
