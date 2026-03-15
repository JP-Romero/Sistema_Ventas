const ctx = document.getElementById('graficoVentas');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'],
    datasets: [{
      label: 'Ventas',
      data: [12, 19, 3, 5, 2],
      backgroundColor: '#2ecc71'
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      title: { display: true, text: 'Ventas de la semana' }
    }
  }
});
