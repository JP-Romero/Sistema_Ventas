/**
 * inventario.js
 * Controla la búsqueda, filtros y paginación de productos
 */

let timer;

/**
 * Busca productos con filtros y paginación
 * @param {number} pagina - Página actual
 */
function buscarProductos(pagina = 1) {
  clearTimeout(timer);

  const categoria = document.getElementById("categoria")?.value || '';
  const estado = document.getElementById("estado")?.value || '';
  const nombre = document.getElementById("nombre_busqueda")?.value || '';
  const precioMin = document.getElementById("precio_min")?.value || '';
  const precioMax = document.getElementById("precio_max")?.value || '';

  // Evitar llamadas innecesarias si no hay datos
  if (!categoria && !estado && !nombre && !precioMin && !precioMax && pagina === 1) return;

  timer = setTimeout(() => {
    const url = new URL('obtener_productos.php', window.location.origin);
    url.searchParams.append('pagina', pagina);
    if (categoria) url.searchParams.append('categoria', categoria);
    if (estado) url.searchParams.append('estado', estado);
    if (nombre) url.searchParams.append('nombre', nombre);
    if (precioMin) url.searchParams.append('precio_min', precioMin);
    if (precioMax) url.searchParams.append('precio_max', precioMax);

    fetch(url)
      .then(response => {
        if (!response.ok) throw new Error(`Error ${response.status}: ${response.statusText}`);
        return response.json();
      })
      .then(data => {
        let html = '';

        if (!data.productos || data.productos.length === 0) {
          html = '<tr><td colspan="8">No se encontraron productos.</td></tr>';
        } else {
          data.productos.forEach(p => {
            const clase_estado = p.estado === 'Activo' ? 'estado-activo' : 'estado-inactivo';
            const imagenSrc = p.imagen ? `img/productos/${p.imagen}` : 'img/productos/sin-imagen.jpg';
            const nombreCortado = p.nombre.length > 30 ? p.nombre.substring(0, 30) + '...' : p.nombre;

            html += `
              <tr>
                <td>${p.id}</td>
                <td>${nombreCortado}</td>
                <td>$${parseFloat(p.precio).toFixed(2)}</td>
                <td>${p.stock}</td>
                <td>${p.categoria_nombre || 'Sin categoría'}</td>
                <td class="${clase_estado}">${p.estado}</td>
                <td><img src="${imagenSrc}" alt="Imagen" width="50" style="border-radius: 4px;"></td>
                <td>
                  <a href="editar_producto.php?id=${p.id}" class="btn mini editar">✏️ Editar</a> |
                  <a href="#" onclick="confirmarEliminacion(${p.id}, '${p.nombre.replace(/'/g, "\\'")}')">🗑️ Eliminar</a>
                </td>
              </tr>
            `;
          });
        }

        let paginacionHtml = '';
        if (data.total_paginas > 1) {
          for (let i = 1; i <= data.total_paginas; i++) {
            paginacionHtml += `<a href="#" onclick="buscarProductos(${i}); return false;">${i}</a>`;
          }
        }

        document.getElementById('resultado-busqueda').innerHTML = `
          <table class="tabla-resultados">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Categoría</th>
                <th>Estado</th>
                <th>Imagen</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              ${html}
            </tbody>
          </table>
          ${data.total_paginas > 1 ? `<div class="paginacion">${paginacionHtml}</div>` : ''}
        `;
      })
      .catch(error => {
        console.error('Error al cargar productos:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'No se pudieron cargar los productos. Revisa la conexión.',
        });
        document.getElementById('resultado-busqueda').innerHTML = '<p>Error al cargar productos.</p>';
      });
  }, 500); // Espera 500ms antes de hacer la búsqueda
}

/**
 * Confirmar eliminación de producto
 * @param {number} id - ID del producto
 * @param {string} nombre - Nombre del producto
 */
function confirmarEliminacion(id, nombre) {
  Swal.fire({
    title: '¿Eliminar "' + nombre + '"?',
    text: 'Esta acción no se puede deshacer.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e74c3c',
    cancelButtonColor: '#aaa',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = 'eliminar_producto.php?id=' + id;
    }
  });
}

// Cargar productos al iniciar
document.addEventListener("DOMContentLoaded", function () {
  buscarProductos();
});