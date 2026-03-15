const rutaImgProductos = 'img/productos/';
let productosBD = [];
let carrito = [];
let categorias = [];

async function cargarProductos() {
  try {
    const res = await fetch('obtener_productos.php');
    if (!res.ok) throw new Error(`Error ${res.status}`);
    productosBD = await res.json();
    mostrarProductos(productosBD);
    extraerCategorias(productosBD);
    renderizarCategorias();
  } catch (e) {
    Swal.fire('Error', 'No se pudieron cargar los productos.', 'error');
    console.error(e);
  }
}

function mostrarProductos(lista) {
  const contenedor = document.getElementById('productosDisponibles');
  contenedor.innerHTML = '';
  lista.forEach(prod => {
    const card = document.createElement('div');
    card.className = 'card-producto';

    const img = document.createElement('img');
    img.src = prod.imagen ? rutaImgProductos + prod.imagen : rutaImgProductos + 'no-disponible.png';
    img.alt = prod.nombre;

    const nombre = document.createElement('div');
    nombre.textContent = prod.nombre;

    const precio = document.createElement('div');
    precio.textContent = `C$ ${Number(prod.precio).toFixed(2)}`;

    card.append(img, nombre, precio);
    card.onclick = () => agregarAlCarrito(prod);

    contenedor.appendChild(card);
  });
}

function agregarAlCarrito(producto) {
  const idx = carrito.findIndex(p => p.id === producto.id);
  if (idx !== -1) carrito[idx].cantidad++;
  else carrito.push({ ...producto, cantidad: 1 });
  
  // 🔔 Animación visual al agregar
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: `${producto.nombre} agregado al carrito`,
    showConfirmButton: false,
    timer: 1200
  });
  renderizarCarrito();
}

function renderizarCarrito() {
  const lista = document.getElementById('carritoLista');
  lista.innerHTML = '';
  carrito.forEach((item, i) => {
    const li = document.createElement('li');
    li.innerHTML = `
      <span>${item.nombre} x${item.cantidad} — C$ ${(item.precio * item.cantidad).toFixed(2)}</span>
      <div>
        <button onclick="cambiarCantidad(${i}, 1)">➕</button>
        <button class="eliminar" onclick="eliminarProducto(${i})">🗑️</button>
      </div>
    `;
    lista.appendChild(li);
  });
}

function cambiarCantidad(index, delta) {
  carrito[index].cantidad = Math.max(1, carrito[index].cantidad + delta);
  renderizarCarrito();
}

function eliminarProducto(index) {
  carrito.splice(index, 1);
  renderizarCarrito();
}

function extraerCategorias(lista) {
  const cats = new Set();
  lista.forEach(p => {
    if (p.categoria) cats.add(p.categoria);
  });
  categorias = Array.from(cats);
}

function renderizarCategorias() {
  const panel = document.getElementById('categoriasLista');
  panel.innerHTML = '';
  categorias.forEach(cat => {
    const btn = document.createElement('button');
    btn.className = 'categoria-btn';
    btn.textContent = cat;
    btn.onclick = () => filtrarPorCategoria(cat);
    panel.appendChild(btn);
  });
}

function filtrarPorCategoria(categoria) {
  const filtrados = productosBD.filter(p => p.categoria === categoria);
  mostrarProductos(filtrados);
}

function aplicarFiltros() {
  const nombre = document.getElementById('busquedaNombre').value.trim().toLowerCase();
  const min = parseFloat(document.getElementById('busquedaMin').value) || 0;
  const max = parseFloat(document.getElementById('busquedaMax').value) || Infinity;

  const filtrados = productosBD.filter(p =>
    p.nombre.toLowerCase().includes(nombre) &&
    p.precio >= min && p.precio <= max
  );
  mostrarProductos(filtrados);
}

document.getElementById('busquedaNombre').addEventListener('input', aplicarFiltros);
document.getElementById('busquedaMin').addEventListener('input', aplicarFiltros);
document.getElementById('busquedaMax').addEventListener('input', aplicarFiltros);

document.getElementById('btnCobrar').addEventListener('click', () => {
  localStorage.setItem('carrito', JSON.stringify(carrito));
  window.location.href = 'cobro.php';
});

// Inicializar
document.addEventListener('DOMContentLoaded', cargarProductos);
