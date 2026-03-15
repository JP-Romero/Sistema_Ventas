const searchName = document.getElementById('search-name');
const searchMinPrice = document.getElementById('search-min-price');
const searchMaxPrice = document.getElementById('search-max-price');
const searchCategory = document.getElementById('search-category');
const centerPanel = document.getElementById('center-panel');

const productList = document.getElementById('product-list');
const subtotalEl = document.getElementById('subtotal');
const ivaEl = document.getElementById('iva');
const totalEl = document.getElementById('total');
let cart = [];

function addProduct(name, price) {
  let existing = cart.find(p => p.name === name);
  if (existing) {
    existing.qty++;
  } else {
    cart.push({ name, price, qty: 1 });
  }
  renderCart();
}

function renderCart() {
  productList.innerHTML = '';
  let subtotal = 0;

  cart.forEach(product => {
    subtotal += product.price * product.qty;

    const div = document.createElement('div');
    div.className = 'product';

    div.innerHTML = `
      <span>${product.name}</span>
      <div class="qty-controls">
        <button aria-label="Disminuir cantidad de ${product.name}" onclick="changeQty('${product.name}', -1)">-</button>
        <span aria-live="polite" aria-atomic="true">${product.qty}</span>
        <button aria-label="Aumentar cantidad de ${product.name}" onclick="changeQty('${product.name}', 1)">+</button>
      </div>
      <span>C$ ${(product.price * product.qty).toFixed(2)}</span>
    `;

    productList.appendChild(div);
  });

  const iva = subtotal * 0.15;
  const total = subtotal + iva;

  subtotalEl.textContent = subtotal.toFixed(2);
  ivaEl.textContent = iva.toFixed(2);
  totalEl.textContent = total.toFixed(2);
}

function changeQty(name, delta) {
  const product = cart.find(p => p.name === name);
  if (!product) return;

  product.qty += delta;
  if (product.qty <= 0) {
    cart = cart.filter(p => p.name !== name);
  }
  renderCart();
}

function filterProducts() {
  const nameVal = searchName.value.trim().toLowerCase();
  const minPrice = parseFloat(searchMinPrice.value) || 0;
  const maxPrice = parseFloat(searchMaxPrice.value) || Number.MAX_SAFE_INTEGER;
  const categoryVal = searchCategory.value.trim().toLowerCase();

  const products = centerPanel.querySelectorAll('.product-img');
  products.forEach(img => {
    const productName = img.getAttribute('data-name');
    const productPrice = parseFloat(img.getAttribute('data-price'));
    const productCategory = img.getAttribute('data-category');

    const matchesName = productName.includes(nameVal);
    const matchesPrice = productPrice >= minPrice && productPrice <= maxPrice;
    const matchesCategory = categoryVal === '' || productCategory.includes(categoryVal);

    if (matchesName && matchesPrice && matchesCategory) {
      img.style.display = '';
    } else {
      img.style.display = 'none';
    }
  });
}

searchName.addEventListener('input', filterProducts);
searchMinPrice.addEventListener('input', filterProducts);
searchMaxPrice.addEventListener('input', filterProducts);
searchCategory.addEventListener('input', filterProducts);

document.getElementById('btnCobrar').addEventListener('click', () => {
  if (cart.length === 0) {
    alert('No hay productos en la lista para cobrar.');
    return;
  }
  alert(`Cobro realizado!\nTotal a pagar: C$ ${totalEl.textContent}`);
  cart = [];
  renderCart();
  filterProducts(); // para refrescar la vista en panel central si quieres
});

renderCart();
filterProducts();
