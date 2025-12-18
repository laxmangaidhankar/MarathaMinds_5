// Initialize Map (India centered – you can change city)
const map = L.map('liveMap').setView([18.5204, 73.8567], 12);

// Tile Layer
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap'
}).addTo(map);

// Zone Areas (Example polygons)
const zones = [
  {
    name: "Zone A",
    color: "green",
    coords: [
      [18.53, 73.85],
      [18.55, 73.88],
      [18.51, 73.90],
      [18.50, 73.86]
    ]
  },
  {
    name: "Zone B",
    color: "orange",
    coords: [
      [18.49, 73.82],
      [18.50, 73.85],
      [18.47, 73.88],
      [18.45, 73.84]
    ]
  }
];

// Draw Zones
zones.forEach(zone => {
  L.polygon(zone.coords, {
    color: zone.color,
    fillOpacity: 0.25
  })
  .addTo(map)
  .bindPopup(`<b>${zone.name}</b><br>Status: Active`);
});

// Worker Markers
const workers = [
  { name: "Worker 1", lat: 18.52, lng: 73.86 },
  { name: "Worker 2", lat: 18.54, lng: 73.88 },
  { name: "Worker 3", lat: 18.49, lng: 73.84 }
];

// Add Workers
workers.forEach(worker => {
  L.marker([worker.lat, worker.lng])
    .addTo(map)
    .bindPopup(`<b>${worker.name}</b><br>Status: Working`);
});
