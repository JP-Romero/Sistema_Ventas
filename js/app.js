const tasaDolar = 36.5;
const rutaImgProductos = 'img/productos/';

let productosFiltrados = [];
let productosFiltradosOriginal = [];
let carrito = [];
let metodoPagoSeleccionado = null;
let paginaActual = 1;
let btnPagar;
let pagosSimples = [];
let pagosMixtos = [];
const productosPorPagina = 8;

function actualizarFechaHora() {
  const ahora = new Date();
  const opcionesFecha = { day: '2-digit', month: '2-digit', year: 'numeric' };
  const opcionesHora = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
  const elemento = document.getElementById('fechaHoraActual');
  if (elemento) {
    elemento.textContent = ahora.toLocaleDateString('es-NI', opcionesFecha) + ' ' + ahora.toLocaleTimeString('es-NI', opcionesHora);
  }
}
setInterval(actualizarFechaHora, 1000);
actualizarFechaHora();

async function cargarProductos() {
  try {
    document.getElementById('spinner').style.display = 'block';
    const response = await fetch('obtener_productos.php');
    if (!response.ok) throw new Error('Error ' + response.status);
    const productos = await response.json();
    return productos;
  } catch (error) {
    Swal.fire('Error', 'No se pudieron cargar los productos.', 'error');
    console.error(error);
    return [];
  } finally {
    document.getElementById('spinner').style.display = 'none';
    document.querySelector('.venta-layout').style.display = 'flex';
  }
}

function guardarCarrito() {
  localStorage.setItem('carrito', JSON.stringify(carrito));
}

function mostrarResultadosBusqueda(lista) {
  const contenedor = document.getElementById('resultado-busqueda');
  contenedor.innerHTML = '';
  if (lista.length === 0) {
    contenedor.innerHTML = '<p>No se encontraron productos.</p>';
    return;
  }
  lista.forEach(producto => {
    const tarjeta = document.createElement('div');
    tarjeta.className = 'card-producto';
    tarjeta.style.position = 'relative';

    const img = document.createElement('img');
    img.src = producto.imagen ? rutaImgProductos + producto.imagen : rutaImgProductos + 'no-disponible.png';
    img.alt = producto.nombre;
    img.onerror = () => { img.onerror = null; img.src = rutaImgProductos + 'no-disponible.png'; };

    const nombre = document.createElement('div');
    nombre.className = 'nombre';
    nombre.textContent = producto.nombre;

    const precio = document.createElement('div');
    precio.className = 'precio';
    precio.textContent = `C$ ${Number(producto.precio).toFixed(2)}`;

    const btnAgregar = document.createElement('button');
    btnAgregar.className = 'btn-float-agregar';
    btnAgregar.title = 'Agregar al carrito';
    btnAgregar.innerHTML = '🛒';
    btnAgregar.onclick = (e) => {
      e.stopPropagation();
      agregarAVenta(producto);
      btnAgregar.classList.add('boton-agregado');
      setTimeout(() => btnAgregar.classList.remove('boton-agregado'), 650);
    };

    tarjeta.appendChild(img);
    tarjeta.appendChild(nombre);
    tarjeta.appendChild(precio);
    tarjeta.appendChild(btnAgregar);

    contenedor.appendChild(tarjeta);
  });
}

function getTotalPaginas() { return Math.ceil(productosFiltrados.length / productosPorPagina); }
function getProductosDePagina(pagina) {
  const start = (pagina - 1) * productosPorPagina;
  return productosFiltrados.slice(start, start + productosPorPagina);
}
function renderizarPaginador() {
  const totalPaginas = getTotalPaginas();
  const pagCont = document.getElementById('paginacion-productos');
  pagCont.innerHTML = '';
  if (totalPaginas <= 1) return;
  if (paginaActual > 1) {
    const prevBtn = document.createElement('button');
    prevBtn.textContent = 'Anterior';
    prevBtn.onclick = () => mostrarResultadosBusquedaPaginado(paginaActual - 1);
    pagCont.appendChild(prevBtn);
  }
  for (let i = 1; i <= totalPaginas; i++) {
    const pageBtn = document.createElement('button');
    pageBtn.textContent = i;
    if (i === paginaActual) {
      pageBtn.style = 'background:#30ea84;color:#fff;font-weight:bold;';
      pageBtn.disabled = true;
    } else {
      pageBtn.onclick = () => mostrarResultadosBusquedaPaginado(i);
    }
    pagCont.appendChild(pageBtn);
  }
  if (paginaActual < totalPaginas) {
    const nextBtn = document.createElement('button');
    nextBtn.textContent = 'Siguiente';
    nextBtn.onclick = () => mostrarResultadosBusquedaPaginado(paginaActual + 1);
    pagCont.appendChild(nextBtn);
  }
}
function filtrarProductosPorCriterios() {
  const nombre = document.getElementById('nombre_busqueda').value.trim().toLowerCase();
  const precioMinStr = document.getElementById('precio_min').value;
  const precioMaxStr = document.getElementById('precio_max').value;
  const precioMin = precioMinStr === '' ? null : parseFloat(precioMinStr);
  const precioMax = precioMaxStr === '' ? null : parseFloat(precioMaxStr);
  productosFiltrados = productosFiltradosOriginal.filter(p => {
    const cumpleNombre = !nombre || p.nombre.toLowerCase().includes(nombre);
    const cumplePrecioMin = precioMin === null || Number(p.precio) >= precioMin;
    const cumplePrecioMax = precioMax === null || Number(p.precio) <= precioMax;
    return cumpleNombre && cumplePrecioMin && cumplePrecioMax;
  });
  paginaActual = 1;
  mostrarResultadosBusquedaPaginado(paginaActual);
}
function mostrarResultadosBusquedaPaginado(pagina) {
  paginaActual = pagina;
  const prods = getProductosDePagina(pagina);
  mostrarResultadosBusqueda(prods);
  renderizarPaginador();
}

function agregarAVenta(producto) {
  const idx = carrito.findIndex(p => p.nombre === producto.nombre);
  if (idx !== -1) carrito[idx].cantidad++;
  else carrito.push({ ...producto, cantidad: 1 });
  guardarCarrito();
  actualizarResumenCarrito();
  actualizarTotales();
  Swal.fire({
    icon: 'success',
    title: 'Producto agregado',
    html: `<strong>${producto.nombre}</strong> (C$ ${Number(producto.precio).toFixed(2)})`,
    timer: 1200,
    showConfirmButton: false
  });
}

function actualizarResumenCarrito() {
  const ul = document.getElementById('listaCarrito');
  ul.innerHTML = '';
  carrito.forEach((item, i) => {
    const li = document.createElement('li');
    li.style.display = "flex";
    li.style.alignItems = "center";
    li.style.gap = "16px";
    li.style.background = "#f6fcfa";
    li.style.padding = "9px 6px";
    li.style.borderRadius = "8px";
    li.style.marginBottom = "8px";
    li.style.borderBottom = "1px solid #dbebdf";
    li.style.width = "100%";

    const nombreDiv = document.createElement('div');
    nombreDiv.style.flex = "1";
    nombreDiv.style.overflow = "hidden";
    nombreDiv.style.display = "flex";
    nombreDiv.style.alignItems = "center";

    const nombreSpan = document.createElement('span');
    nombreSpan.textContent = item.nombre;
    nombreSpan.title = item.nombre;
    nombreSpan.style.fontWeight = "bold";
    nombreSpan.style.whiteSpace = "normal";
    nombreSpan.style.overflowWrap = "break-word";
    nombreSpan.style.fontSize = "1.07em";
    nombreSpan.style.flex = "1 1 auto";
    nombreDiv.appendChild(nombreSpan);

    const accionesDiv = document.createElement('div');
    accionesDiv.style.display = "flex";
    accionesDiv.style.alignItems = "center";
    accionesDiv.style.gap = "3px";
    accionesDiv.style.flexShrink = "0";

    const btnMenos = document.createElement('button');
    btnMenos.className = 'btn-mini-cantidad';
    btnMenos.textContent = '−';
    btnMenos.onclick = () => cambiarCantidad(i, -1);

    const cantidad = document.createElement('span');
    cantidad.textContent = item.cantidad;
    cantidad.style.minWidth = "28px";
    cantidad.style.textAlign = "center";
    cantidad.style.margin = "0 2px";
    cantidad.style.fontWeight = "bold";

    const btnMas = document.createElement('button');
    btnMas.className = 'btn-mini-cantidad';
    btnMas.textContent = '+';
    btnMas.onclick = () => cambiarCantidad(i, 1);

    const total = document.createElement('span');
    total.textContent = `C$ ${(item.precio * item.cantidad).toFixed(2)}`;
    total.style.marginLeft = "7px";
    total.style.fontWeight = "bold";
    total.style.minWidth = "62px";
    total.style.textAlign = "right";

    const btnEliminar = document.createElement('button');
    btnEliminar.className = 'btn-eliminar-prod';
    btnEliminar.innerHTML = '🗑️';
    btnEliminar.title = "Eliminar";
    btnEliminar.onclick = () => eliminarProductoCarrito(i);

    accionesDiv.appendChild(btnMenos);
    accionesDiv.appendChild(cantidad);
    accionesDiv.appendChild(btnMas);
    accionesDiv.appendChild(total);
    accionesDiv.appendChild(btnEliminar);

    li.appendChild(nombreDiv);
    li.appendChild(accionesDiv);

    ul.appendChild(li);
  });
}

function cambiarCantidad(idx, delta) {
  if (idx < 0 || idx >= carrito.length) return;
  carrito[idx].cantidad = Math.max(1, carrito[idx].cantidad + delta);
  guardarCarrito();
  actualizarResumenCarrito();
  actualizarTotales();
}

function eliminarProductoCarrito(idx) {
  if (idx < 0 || idx >= carrito.length) return;
  carrito.splice(idx, 1);
  guardarCarrito();
  actualizarResumenCarrito();
  actualizarTotales();
}

let aplicarIVA = true;

function obtenerDescuento(totalAntesDescuento) {
  const descuentoInput = document.getElementById('descuentoInput');
  const pct = parseFloat(descuentoInput.value) || 0;
  return totalAntesDescuento * (pct / 100);
}

function actualizarTotales() {
  const subtotal = carrito.reduce((acc, item) => acc + item.precio * item.cantidad, 0);
  const iva = aplicarIVA ? subtotal * 0.15 : 0;
  const descuento = obtenerDescuento(subtotal + iva);
  const total = subtotal + iva - descuento;

  document.getElementById('subtotalTexto').textContent = `C$ ${subtotal.toFixed(2)}`;
  document.getElementById('ivaTexto').textContent = `C$ ${iva.toFixed(2)}`;
  document.getElementById('descTexto').textContent = `C$ ${descuento.toFixed(2)}`;
  document.getElementById('totalTexto').textContent = `C$ ${total.toFixed(2)}`;

  if (metodoPagoSeleccionado === 'dolares') {
    document.getElementById('totalEnDolares').textContent = `US$ ${(total / tasaDolar).toFixed(2)}`;
  } else {
    document.getElementById('totalEnDolares').textContent = '';
  }

  actualizarInterfacePagosMixtos();

  if (btnPagar) btnPagar.disabled = true; // Se habilitará al ingresar monto
}

function seleccionarMetodoPago(metodo) {
  metodoPagoSeleccionado = metodo;

  const panelMontoSimple = document.getElementById('panelMontoSimple');
  const panelPagosMixtos = document.getElementById('panelPagosMixtos');
  const contPagosSimples = document.getElementById('panelPagosSimples');
  const inputMontoSimple = document.getElementById('montoPagadoSimple');
  const inputMontoEntregadoCambio = document.getElementById('montoEntregadoCambio');
  const cambioMostrado = document.getElementById('cambioMostrado');

  if (inputMontoSimple) inputMontoSimple.value = '';
  if (inputMontoEntregadoCambio) inputMontoEntregadoCambio.value = '';
  if (cambioMostrado) cambioMostrado.value = (metodo === 'dolares' ? 'US$' : 'C$') + ' 0.00';

  if (metodo === 'mixto') {
    pagosSimples = [];
    if(panelMontoSimple) panelMontoSimple.style.display = 'none';
    if(contPagosSimples) contPagosSimples.style.display = 'none';
    if(panelPagosMixtos) panelPagosMixtos.style.display = 'block';
    if(inputMontoSimple) inputMontoSimple.disabled = true;
    if(inputMontoEntregadoCambio) inputMontoEntregadoCambio.disabled = true;
    actualizarInterfacePagosMixtos();
    if(btnPagar) btnPagar.disabled = true;
  } else if (metodo === 'efectivo' || metodo === 'dolares' || metodo === 'tarjeta') {
    pagosMixtos = [];
    if(panelPagosMixtos) panelPagosMixtos.style.display = 'none';
    if(panelMontoSimple) panelMontoSimple.style.display = (metodo === 'tarjeta') ? 'none' : 'block';
    if(contPagosSimples) contPagosSimples.style.display = (metodo === 'tarjeta') ? 'none' : 'block';
    if(inputMontoSimple) inputMontoSimple.disabled = (metodo === 'tarjeta');
    if(inputMontoEntregadoCambio) inputMontoEntregadoCambio.disabled = (metodo === 'tarjeta');

    if(inputMontoSimple && !inputMontoSimple.disabled) inputMontoSimple.focus();

    actualizarInterfacePagosSimples();
    if(btnPagar) btnPagar.disabled = (metodo === 'tarjeta') ? false : true;
  } else {
    pagosSimples = [];
    pagosMixtos = [];
    if(panelMontoSimple) panelMontoSimple.style.display = 'none';
    if(contPagosSimples) contPagosSimples.style.display = 'none';
    if(panelPagosMixtos) panelPagosMixtos.style.display = 'none';
    if(inputMontoSimple) inputMontoSimple.disabled = true;
    if(inputMontoEntregadoCambio) inputMontoEntregadoCambio.disabled = true;
    if(btnPagar) btnPagar.disabled = true;
  }
}

function actualizarInterfacePagosSimples() {
  const cont = document.getElementById('listaPagosSimples');
  if (!cont) return;

  cont.innerHTML = '';
  if (pagosSimples.length === 0) {
    cont.textContent = 'No hay pagos añadidos.';
  } else {
    pagosSimples.forEach(p => {
      let texto = '';
      if (p.moneda === 'US$') texto = `Efectivo US$: US$ ${p.monto.toFixed(2)} (C$ ${(p.monto * tasaDolar).toFixed(2)})`;
      else if (p.metodo === 'tarjeta') texto = `Tarjeta: C$ ${p.monto.toFixed(2)}`;
      else texto = `Efectivo: C$ ${p.monto.toFixed(2)}`;
      const div = document.createElement('div');
      div.textContent = texto;
      cont.appendChild(div);
    });
  }

  const totalTexto = document.getElementById('totalTexto').textContent || 'C$ 0.00';
  const totalVenta = parseFloat(totalTexto.replace('C$', '').trim()) || 0;
  const totalPagado = pagosSimples.reduce((acc, p) => acc + (p.moneda === 'US$' ? p.monto * tasaDolar : p.monto), 0);
  const saldo = Math.max(0, totalVenta - totalPagado);
  const cambio = Math.max(0, totalPagado - totalVenta);

  const saldoDiv = document.createElement('div');
  saldoDiv.style.fontWeight = 'bold';
  saldoDiv.style.marginTop = '10px';
  saldoDiv.textContent = `Saldo pendiente: C$ ${saldo.toFixed(2)}`;
  cont.appendChild(saldoDiv);

  const cambioDiv = document.createElement('div');
  cambioDiv.style.fontWeight = 'bold';
  cambioDiv.style.marginTop = '5px';
  cambioDiv.textContent = `Cambio a entregar: C$ ${cambio.toFixed(2)}`;
  cont.appendChild(cambioDiv);

  if(btnPagar) btnPagar.disabled = saldo > 0;
}

function actualizarInterfacePagosMixtos() {
  const cont = document.getElementById('listaPagosMixtos');
  if (!cont) return;

  cont.innerHTML = '';
  if (pagosMixtos.length === 0) {
    cont.textContent = 'No hay pagos mixtos añadidos.';
  } else {
    pagosMixtos.forEach(p => {
      let texto = '';
      if (p.moneda === 'US$') {
        texto = `Efectivo US$: US$ ${p.monto.toFixed(2)} (C$ ${(p.monto * tasaDolar).toFixed(2)})`;
      } else if (p.metodo === 'tarjeta') {
        texto = `Tarjeta: C$ ${p.monto.toFixed(2)}`;
      } else {
        texto = `Efectivo: C$ ${p.monto.toFixed(2)}`;
      }
      const div = document.createElement('div');
      div.textContent = texto;
      cont.appendChild(div);
    });
  }
}

function confirmarVenta() {
  if (carrito.length === 0) {
    Swal.fire('Atención', 'El carrito está vacío.', 'warning');
    return;
  }
  if (!metodoPagoSeleccionado) {
    Swal.fire('Atención', 'Selecciona un método de pago.', 'warning');
    return;
  }

  if (metodoPagoSeleccionado === 'mixto') {
    const saldo = calcularSaldoPendienteMixto();
    if (saldo > 0) {
      Swal.fire('Error', `Falta pagar C$ ${saldo.toFixed(2)} para completar la venta.`, 'error');
      return;
    }
    confirmarVentaConPagos(pagosMixtos);
  } else {
    if (metodoPagoSeleccionado === 'tarjeta') {
      pagosSimples = [{ metodo: 'tarjeta', moneda: 'C$', monto: calcularTotalVenta() }];
      confirmarVentaConPagos(pagosSimples);
      return;
    }

    const montoInput = document.getElementById('montoPagadoSimple');
    const montoActual = parseFloat(montoInput.value);
    if (isNaN(montoActual) || montoActual <= 0) {
      Swal.fire('Error', 'Ingrese un monto válido para el pago.', 'error');
      return;
    }

    let metodoPago = metodoPagoSeleccionado;
    let moneda = 'C$';
    if (metodoPago === 'dolares') {
      metodoPago = 'efectivoUSD';
      moneda = 'US$';
    }

    const idx = pagosSimples.findIndex(p => p.metodo === metodoPago);
    if (idx !== -1) pagosSimples[idx].monto += montoActual;
    else pagosSimples.push({ metodo: metodoPago, moneda, monto: montoActual });

    montoInput.value = '';
    actualizarInterfacePagosSimples();

    const saldo = calcularSaldoPendienteSimple();
    if (saldo > 0) {
      Swal.fire('Atención', `Falta pagar C$ ${saldo.toFixed(2)} para completar la venta.`, 'info');
      return;
    }

    confirmarVentaConPagos(pagosSimples);
  }
}

function calcularTotalVenta(){
  const totalTexto = document.getElementById('totalTexto').textContent || "C$ 0.00";
  return parseFloat(totalTexto.replace('C$', '').trim()) || 0;
}

function calcularSaldoPendienteMixto() {
  const totalVenta = calcularTotalVenta();
  const totalPagado = pagosMixtos.reduce((acc, p) => acc + (p.moneda === 'US$' ? p.monto * tasaDolar : p.monto), 0);
  return Math.max(0, totalVenta - totalPagado);
}

function calcularSaldoPendienteSimple() {
  const totalVenta = calcularTotalVenta();
  const totalPagado = pagosSimples.reduce((acc, p) => acc + (p.moneda === 'US$' ? p.monto * tasaDolar : p.monto), 0);
  return Math.max(0, totalVenta - totalPagado);
}

function confirmarVentaConPagos(pagos){
  let resumenPagosHTML = pagos.map(p => {
    if(p.metodo === 'efectivoUSD') return `Efectivo Dólares: US$ ${p.monto.toFixed(2)} (C$ ${(p.monto * tasaDolar).toFixed(2)})`;
    if(p.metodo === 'tarjeta') return `Tarjeta: C$ ${p.monto.toFixed(2)}`;
    return `Efectivo: C$ ${p.monto.toFixed(2)}`;
  }).join('<br>');

  const totalVenta = calcularTotalVenta();
  const totalPagado = pagos.reduce((acc, p) => acc + (p.moneda === 'US$' ? p.monto * tasaDolar : p.monto), 0);
  const cambio = Math.max(0, totalPagado - totalVenta);
  const mensajeCambio = cambio > 0 ? `<br><b>Cambio a entregar: C$ ${cambio.toFixed(2)}</b>` : '';

  Swal.fire({
    title: 'Confirmar venta',
    html: resumenPagosHTML + mensajeCambio + '<br><br>¿Confirmar esta venta?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, confirmar',
    cancelButtonText: 'Cancelar'
  }).then(result => {
    if(result.isConfirmed){
      enviarVenta(pagos)
        .then(() => {
          Swal.fire('Éxito', 'Venta registrada correctamente.', 'success');
          limpiarVenta();
        })
        .catch(() => {
          Swal.fire('Error', 'No se pudo registrar la venta.', 'error');
        });
    }
  });
}

async function enviarVenta(pagos){
  const productosEnvio = carrito.map(p => ({
    nombre: p.nombre,
    cantidad: p.cantidad,
    precio: Number(p.precio)
  }));

  const payload = {
    productos: productosEnvio,
    pagos: pagos,
    total_venta: calcularTotalVenta(),
    cajero: document.getElementById('usuario-info').textContent.replace(/^Usuario:\s*/, '') || 'admin'
  };

  const respuesta = await fetch('procesar_venta.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });

  const data = await respuesta.json();

  if (!respuesta.ok || data.error) throw new Error(data.error || 'Error en la venta.');
}

function limpiarVenta(){
  carrito = [];
  pagosSimples = [];
  pagosMixtos = [];
  guardarCarrito();
  actualizarResumenCarrito();
  actualizarTotales();
  seleccionarMetodoPago(null);
  const montoPagadoSimple = document.getElementById('montoPagadoSimple');
  const montoEntregadoCambio = document.getElementById('montoEntregadoCambio');
  const cambioMostrado = document.getElementById('cambioMostrado');
  if (montoPagadoSimple) montoPagadoSimple.value = '';
  if (montoEntregadoCambio) montoEntregadoCambio.value = '';
  if (cambioMostrado) cambioMostrado.value = 'C$ 0.00';
  actualizarInterfacePagosSimples();
  actualizarInterfacePagosMixtos();
}

document.addEventListener('DOMContentLoaded', async () => {
  btnPagar = document.getElementById('btnPagar');
  if(btnPagar){
    btnPagar.disabled = true;
    btnPagar.addEventListener('click', confirmarVenta);
  }

  carrito = JSON.parse(localStorage.getItem('carrito')) || [];
  productosFiltradosOriginal = await cargarProductos();
  productosFiltrados = productosFiltradosOriginal.slice();
  paginaActual = 1;
  mostrarResultadosBusquedaPaginado(paginaActual);
  actualizarResumenCarrito();
  actualizarTotales();

  document.getElementById('nombre_busqueda').addEventListener('input', filtrarProductosPorCriterios);
  document.getElementById('precio_min').addEventListener('input', filtrarProductosPorCriterios);
  document.getElementById('precio_max').addEventListener('input', filtrarProductosPorCriterios);

  const chkIVA = document.getElementById('ivaToggle');
  if(chkIVA){
    chkIVA.addEventListener('change', e => {
      aplicarIVA = e.target.checked;
      actualizarTotales();
    });
  }

  const inputDesc = document.getElementById('descuentoInput');
  if(inputDesc){
    inputDesc.addEventListener('input', () => actualizarTotales());
  }

  const montoPagadoSimple = document.getElementById('montoPagadoSimple');
  if(montoPagadoSimple){
    montoPagadoSimple.addEventListener('input', e => {
      const valor = parseFloat(e.target.value) || 0;
      const total = calcularTotalVenta();
      const montoConvertido = (metodoPagoSeleccionado === 'dolares') ? valor * tasaDolar : valor;
      const saldo = total - montoConvertido;
      const cambio = valor - (metodoPagoSeleccionado === 'dolares' ? total / tasaDolar : total);
      const simbolo = metodoPagoSeleccionado === 'dolares' ? 'US$' : 'C$';

      const montoEntregadoCambio = document.getElementById('montoEntregadoCambio');
      const cambioMostrado = document.getElementById('cambioMostrado');
      if(montoEntregadoCambio) montoEntregadoCambio.value = `${simbolo} ${valor.toFixed(2)}`;
      if(cambioMostrado) cambioMostrado.value = `${simbolo} ${cambio.toFixed(2)}`;

      if(btnPagar) btnPagar.disabled = valor <= 0;
    });
  }

  seleccionarMetodoPago(null);
});
