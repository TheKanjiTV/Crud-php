document.addEventListener('DOMContentLoaded', function() {
    const basePath = window.location.pathname.toLowerCase().includes('/crud-php/') ? '/Crud-php' : '';
    const apiBase = basePath + '/api';

    const userRole = ((document.body && document.body.dataset && document.body.dataset.role)
        ? document.body.dataset.role
        : 'Guest').toLowerCase();

    const canCreate = userRole === 'admin' || userRole === 'user';
    const canUpdate = canCreate;
    const canDelete = userRole === 'admin';

    let productsById = new Map();

    const app = document.getElementById('app');
    const formContainer = document.getElementById('form-container');
    const productForm = document.getElementById('product-form');
    const formTitle = document.getElementById('form-title');
    const createBtn = document.getElementById('create-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const productIdInput = document.getElementById('product-id');
    const productNameInput = document.getElementById('productName');
    const priceInput = document.getElementById('price');

    function getApiErrorMessage(error, fallbackMessage) {
        if (error && error.response && error.response.data) {
            if (typeof error.response.data === 'string') return error.response.data;
            if (error.response.data.message) return error.response.data.message;
        }
        return fallbackMessage;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showForm(isEdit, data) {
        if (!formContainer || !formTitle || !productIdInput || !productNameInput || !priceInput) {
            return;
        }

        formTitle.textContent = isEdit ? 'Edit Product' : 'Create Product';
        productIdInput.value = data && data.id ? data.id : '';
        productNameInput.value = data && data.name ? data.name : '';
        priceInput.value = data && data.price ? data.price : '';
        formContainer.style.display = 'block';
    }

    function hideForm() {
        if (!formContainer || !productForm) {
            return;
        }
        formContainer.style.display = 'none';
        productForm.reset();
    }

    function renderProducts() {
        if (!app) return;

        axios.get(apiBase + '/read.php')
            .then(function(response) {
                const products = Array.isArray(response.data) ? response.data : [];
                productsById = new Map();

                let html = '<table class="min-w-full table-auto">';
                html += '<thead><tr class="bg-gray-800">';
                html += '<th class="px-6 py-2"><span class="text-gray-300">ID</span></th>';
                html += '<th class="px-6 py-2"><span class="text-gray-300">Code</span></th>';
                html += '<th class="px-6 py-2"><span class="text-gray-300">Name</span></th>';
                html += '<th class="px-6 py-2"><span class="text-gray-300">Price</span></th>';
                if (canUpdate || canDelete) {
                    html += '<th class="px-6 py-2"><span class="text-gray-300">Actions</span></th>';
                }
                html += '</tr></thead>';
                html += '<tbody class="bg-gray-200">';

                products.forEach(function(product) {
                    productsById.set(String(product.id), product);

                    html += '<tr class="bg-white border-4 border-gray-200 text-center">';
                    html += '<td class="px-6 py-2">' + escapeHtml(product.id) + '</td>';
                    html += '<td class="px-6 py-2">' + escapeHtml(product.productCode) + '</td>';
                    html += '<td class="px-6 py-2">' + escapeHtml(product.productName) + '</td>';
                    html += '<td class="px-6 py-2">' + escapeHtml(product.price) + '</td>';

                    if (canUpdate || canDelete) {
                        html += '<td class="px-6 py-2">';
                        if (canUpdate) {
                            html += '<button class="edit-btn bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded mr-2" data-id="' + escapeHtml(product.id) + '">Edit</button>';
                        }
                        if (canDelete) {
                            html += '<button class="delete-btn bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded" data-id="' + escapeHtml(product.id) + '">Delete</button>';
                        }
                        html += '</td>';
                    }

                    html += '</tr>';
                });

                html += '</tbody></table>';
                app.innerHTML = html;
            })
            .catch(function(error) {
                console.error(error);
                app.innerHTML = '<div class="p-4 text-red-600">Unable to load products.</div>';
            });
    }

    if (createBtn) {
        if (!canCreate) {
            createBtn.disabled = true;
            createBtn.classList.add('opacity-50', 'cursor-not-allowed');
            createBtn.title = 'Guests are read-only.';
        }

        createBtn.addEventListener('click', function() {
            if (!canCreate) {
                alert('Forbidden: your account is read-only.');
                return;
            }
            showForm(false, {});
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            hideForm();
        });
    }

    if (app) {
        app.addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-btn')) {
                if (!canUpdate) {
                    alert('Forbidden: your account is read-only.');
                    return;
                }

                const id = e.target.dataset.id;
                const product = productsById.get(String(id));
                if (!product) {
                    alert('Product not found. Please refresh.');
                    return;
                }

                showForm(true, {
                    id: product.id,
                    name: product.productName,
                    price: product.price,
                });
            }

            if (e.target.classList.contains('delete-btn')) {
                if (!canDelete) {
                    alert('Forbidden: only admin can delete.');
                    return;
                }

                const id = e.target.dataset.id;
                if (!confirm('Are you sure you want to delete this product?')) {
                    return;
                }

                axios.post(apiBase + '/delete.php', { id: id })
                    .then(function() {
                        renderProducts();
                    })
                    .catch(function(error) {
                        alert(getApiErrorMessage(error, 'Error deleting product.'));
                    });
            }
        });
    }

    if (productForm) {
        productForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const id = productIdInput ? productIdInput.value : '';
            const name = productNameInput ? productNameInput.value : '';
            const price = priceInput ? priceInput.value : '';

            if (String(name).trim() === '') {
                alert('Product name is required.');
                return;
            }

            if (Number.isNaN(parseFloat(price))) {
                alert('Price must be a number.');
                return;
            }

            if (!id && !canCreate) {
                alert('Forbidden: your account is read-only.');
                return;
            }

            if (id && !canUpdate) {
                alert('Forbidden: your account is read-only.');
                return;
            }

            const payload = { productName: name, price: price };
            if (id) {
                payload.id = id;
            }

            const url = id ? (apiBase + '/update.php') : (apiBase + '/create.php');
            axios.post(url, payload)
                .then(function() {
                    hideForm();
                    renderProducts();
                })
                .catch(function(error) {
                    alert(getApiErrorMessage(error, 'Error saving product.'));
                });
        });
    }

    renderProducts();
});
