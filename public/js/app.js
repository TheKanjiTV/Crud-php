document.addEventListener('DOMContentLoaded', function() {
    const basePath = window.location.pathname.toLowerCase().includes('/crud-php/') ? '/Crud-php' : '';
    const apiBase = basePath + '/api';
    const currencyFormat = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

    const userRole = ((document.body && document.body.dataset && document.body.dataset.role) ? document.body.dataset.role : 'guest').toLowerCase();
    const canCreate = userRole === 'user' || userRole === 'admin';
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

    const hasZod = typeof z !== 'undefined' && z && typeof z.object === 'function';
    const productSchema = hasZod
        ? z.object({
            productName: z.string().min(1, { message: "Product name is required" }),
            price: z.string().refine(val => !isNaN(parseFloat(val)), { message: "Price must be a number" }).transform(val => parseFloat(val))
        })
        : null;

    function getApiErrorMessage(error, fallbackMessage) {
        if (error && error.response && error.response.data) {
            if (typeof error.response.data === 'string') return error.response.data;
            if (error.response.data.message) return error.response.data.message;
        }
        return fallbackMessage;
    }

    if (createBtn && !canCreate) {
        createBtn.disabled = true;
        createBtn.classList.add('opacity-50', 'cursor-not-allowed');
        createBtn.title = 'Guests are read-only. Login as User/Admin to create products.';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderProducts() {
        axios.get(apiBase + '/read.php')
            .then(function(response) {
                const products = response.data;
                productsById = new Map();
                let html = '<table class="min-w-full table-auto">';
                html += '<thead class="justify-between"><tr class="bg-gray-800">';
                html += '<th class="px-16 py-2"><span class="text-gray-300">ID</span></th>';
                html += '<th class="px-16 py-2"><span class="text-gray-300">Code</span></th>';
                html += '<th class="px-16 py-2"><span class="text-gray-300">Name</span></th>';
                html += '<th class="px-16 py-2"><span class="text-gray-300">Price</span></th>';
                if (canUpdate || canDelete) {
                    html += '<th class="px-16 py-2"><span class="text-gray-300">Actions</span></th>';
                }
                html += '</tr></thead>';
                html += '<tbody class="bg-gray-200">';
                products.forEach(function(product) {
                    if (!product.deleted_at) {
                        productsById.set(String(product.id), product);
                        html += `
                            <tr class="bg-white border-4 border-gray-200 text-center">
                                <td class="px-16 py-2">${escapeHtml(product.id)}</td>
                                <td class="px-16 py-2">${escapeHtml(product.productCode)}</td>
                                <td class="px-16 py-2">${escapeHtml(product.productName)}</td>
                                <td class="px-16 py-2">${escapeHtml(product.price)}</td>
                                ${(canUpdate || canDelete) ? `
                                <td class="px-16 py-2">
                                    ${canUpdate ? `<button class="edit-btn bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded" data-id="${escapeHtml(product.id)}">Edit</button>` : ''}
                                    ${canDelete ? `<button class="delete-btn bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded" data-id="${product.id}">Delete</button>` : ''}
                                </td>
                                ` : ''}
                            </tr>
                        `;
                    }
                });
                html += '</tbody></table>';
                app.innerHTML = html;
            })
            .catch(function(error) {
                console.error(error);
            });
    }

    function setText(el, text) {
        if (el) el.textContent = text;
    }

    function showForm(isEdit = false, data = {}) {
        formTitle.textContent = isEdit ? 'Edit Product' : 'Create Product';
        productIdInput.value = data.id || '';
        productNameInput.value = data.name || '';
        priceInput.value = data.price || '';
        formContainer.style.display = 'block';
    }

    function hideForm() {
        formContainer.style.display = 'none';
        productForm.reset();
    }

    if (createBtn) {
        createBtn.addEventListener('click', function() {
            if (!canCreate) {
                alert('Forbidden: your account is read-only.');
                return;
            }
            showForm();
        });
    }

    cancelBtn.addEventListener('click', function() {
        hideForm();
    });

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
            showForm(true, { id: product.id, name: product.productName, price: product.price });
        }

        if (e.target.classList.contains('delete-btn')) {
            if (!canDelete) {
                alert('Forbidden: only admin can delete.');
                return;
            }
            const id = e.target.dataset.id;
            if (confirm('Are you sure you want to delete this product?')) {
                axios.post(apiBase + '/delete.php', { id: id })
                    .then(function(response) {
                        renderProducts();
                    })
                    .catch(function(error) {
                        alert(getApiErrorMessage(error, 'Error deleting product.'));
                    });
            }
        }
    });

    productForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const id = productIdInput.value;
        const name = productNameInput.value;
        const price = priceInput.value;

        if (productSchema) {
            const validation = productSchema.safeParse({ productName: name, price: price });
            if (!validation.success) {
                const errors = validation.error.errors.map(err => err.message).join('\n');
                alert(errors);
                return;
            }
        }

        const url = id ? (apiBase + '/update.php') : (apiBase + '/create.php');
        const payload = { productName: name, price: price };
        if (id) {
            payload.id = id;
        }

        axios.post(url, payload)
            .then(function(response) {
                hideForm();
                renderProducts();
            })
            .catch(function(error) {
                alert(getApiErrorMessage(error, 'Error saving product.'));
            });
    });

    renderProducts();
});

            if (confirm('Are you sure you want to delete this product?')) {
                axios.post(apiBase + '/delete.php', { id: id })
                    .then(function(response) {
                        renderProducts();
                    })
                    .catch(function(error) {
                        console.error(error);
                        alert(getApiErrorMessage(error, 'Error deleting product'));
                    });
            }
        }
    });

    productForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        try {
            let validatedData;
            if (productSchema) {
                validatedData = productSchema.parse(data);
            } else {
                if (!data.productName || String(data.productName).trim() === '') {
                    throw new Error('Product name is required');
                }
                const parsedPrice = parseFloat(String(data.price));
                if (Number.isNaN(parsedPrice)) {
                    throw new Error('Price must be a number');
                }
                validatedData = { productName: data.productName, price: parsedPrice };
            }
            const id = productIdInput.value;

            if (!id && !canCreate) {
                alert('Forbidden: your account is read-only.');
                return;
            }
            if (id && !canUpdate) {
                alert('Forbidden: your account is read-only.');
                return;
            }

            let request;
            if (id) { // Update
                validatedData.id = id;
                request = axios.post(apiBase + '/update.php', validatedData);
            } else { // Create
                request = axios.post(apiBase + '/create.php', validatedData);
            }

            request.then(function(response) {
                    hideForm();
                    renderProducts();
                })
                .catch(function(error) {
                    console.error(error);
                    alert(getApiErrorMessage(error, 'Error saving product'));
                });

        } catch (err) {
            if (hasZod && err instanceof z.ZodError) {
                alert(err.errors.map(e => e.message).join('\n'));
                return;
            }
            if (err instanceof Error) {
                alert(err.message);
                return;
            }
            alert('Invalid form data');
        }
    });

    renderProducts();
    loadAnalytics();
});
