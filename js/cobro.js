document.addEventListener("DOMContentLoaded", function() {
  let metodoSeleccionado = "efectivo";
  const botonMetodoPago = document.querySelectorAll('.metodoPagoBtn');
  botonMetodoPago.forEach(btn => {
    btn.addEventListener('click', function() {
      metodoSeleccionado = btn.dataset.metodo;
      ['panel-efectivo','panel-tarjeta','panel-mixto'].forEach(id => {
        document.getElementById(id).style.display = 'none';
      });
      if (metodoSeleccionado === "efectivo")
        document.getElementById('panel-efectivo').style.display = 'block';
      else if (metodoSeleccionado === "tarjeta")
        document.getElementById('panel-tarjeta').style.display = 'block';
      else if (metodoSeleccionado === "mixto")
        document.getElementById('panel-mixto').style.display = 'block';
    });
  });
  // Default selection
  document.querySelector('.metodoPagoBtn[data-metodo="efectivo"]').click();

  document.getElementById('formCobro').addEventListener('submit', function(e) {
    e.preventDefault();
    const totalStr = document.getElementById('modalTotalCobro').textContent || 'C$ 0.00';
    const total = parseFloat(totalStr.replace(/[^\d.]/g,''));
    let pagado = 0, cambio = 0, error = "";

    if(metodoSeleccionado === "efectivo") {
      pagado = parseFloat(document.getElementById('inputEfectivo').value || 0);
      if (isNaN(pagado) || pagado < total) error = "El monto entregado es insuficiente.";
      cambio = pagado - total;
    } else if (metodoSeleccionado === "tarjeta") {
      pagado = parseFloat(document.getElementById('inputTarjeta').value || 0);
      if (isNaN(pagado) || pagado < total) error = "El monto a debitar debe ser igual al total.";
      cambio = 0;
    } else if (metodoSeleccionado === "mixto") {
      const efec = parseFloat(document.getElementById('inputEfectivoMixto').value || 0);
      const tarj = parseFloat(document.getElementById('inputTarjetaMixto').value || 0);
      pagado = efec + tarj;
      if (isNaN(efec) || isNaN(tarj) || pagado < total) error = "La suma de efectivo y tarjeta es insuficiente.";
      cambio = efec > (total - tarj) ? efec - (total - tarj) : 0;
    }

    if(error) {
      Swal.fire("Atención", error, 'warning');
      return;
    }

    if (cambio > 0) {
      document.getElementById('panel-cambio').innerHTML = 
        `<div style="font-weight:bold;font-size:1.12em;color:#2ecc71;">Cambio: C$ ${cambio.toFixed(2)}</div>`;
    } else {
      document.getElementById('panel-cambio').innerHTML = "";
    }

    Swal.fire({
      icon: 'success',
      title: 'Venta realizada ✔️',
      html: `Cobrado correctamente.<br>${cambio>0 ? 'Cambio: C$ ' + cambio.toFixed(2) : ''}`
    }).then(() => {
      // Cierra modal y limpia formulario
      document.getElementById('modalCobro').style.display = 'none';
      document.querySelector('.venta-layout').style.filter = '';
      document.getElementById('formCobro').reset();
      document.querySelector('.metodoPagoBtn[data-metodo="efectivo"]').click();
      document.getElementById('panel-cambio').innerHTML = '';

      // Aquí limpia carrito y actualización, llamar función pública del app.js si la defines
      // Ejemplo:
      if (typeof limpiarVenta === "function") limpiarVenta();
    });
  });
});
