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

    const summaryUsers = document.getElementById('summary-users');
    const summaryProducts = document.getElementById('summary-products');
    const summaryOrders = document.getElementById('summary-orders');
    const summaryRevenue = document.getElementById('summary-revenue');
    const analyticsWarning = document.getElementById('analytics-warning');
    const analyticsDbBadge = document.getElementById('analytics-db-badge');

    const monthlyRevenueCanvas = document.getElementById('monthlyRevenueChart');
    const orderStatusCanvas = document.getElementById('orderStatusChart');
    const topCategoriesCanvas = document.getElementById('topCategoriesChart');
    const paymentMethodsCanvas = document.getElementById('paymentMethodsChart');

    const charts = {
        monthlyRevenue: null,
        orderStatus: null,
        topCategories: null,
        paymentMethods: null
    };

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

    function destroyChart(chartRefKey) {
        if (charts[chartRefKey]) {
            charts[chartRefKey].destroy();
            charts[chartRefKey] = null;
        }
    }

    function renderAnalyticsChart(canvasEl, chartRefKey, chartType, labels, values, chartLabel, colors) {
        if (!canvasEl || typeof Chart === 'undefined') {
            return;
        }

        const numericValues = Array.isArray(values) ? values.map(v => Number(v) || 0) : [];
        const maxValue = numericValues.length > 0 ? Math.max(...numericValues) : 0;

        destroyChart(chartRefKey);
        charts[chartRefKey] = new Chart(canvasEl, {
            type: chartType,
            data: {
                labels: labels,
                datasets: [{
                    label: chartLabel,
                    data: numericValues,
                    backgroundColor: colors,
                    borderColor: Array.isArray(colors) ? '#ffffff' : colors,
                    borderWidth: 2,
                    tension: 0.3,
                    fill: chartType === 'line',
                    pointRadius: chartType === 'line' ? 3 : 0,
                    pointHoverRadius: chartType === 'line' ? 5 : 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: (chartType === 'doughnut' || chartType === 'pie') ? 1.8 : 2.2,
                plugins: {
                    legend: {
                        display: chartType !== 'bar'
                    }
                },
                scales: (chartType === 'line' || chartType === 'bar') ? {
                    y: {
                        beginAtZero: true,
                        suggestedMax: maxValue > 0 ? maxValue * 1.2 : 10
                    }
                } : undefined
            }
        });
    }

    function setText(el, text) {
        if (el) el.textContent = text;
    }

    function renderAnalytics(data) {
        const summary = data.summary || {};
        const chartData = data.charts || {};

        setText(summaryUsers, String(summary.users || 0));
        setText(summaryProducts, String(summary.products || 0));
        setText(summaryOrders, String(summary.orders || 0));
        setText(summaryRevenue, currencyFormat.format(Number(summary.totalRevenue || 0)));

        if (analyticsDbBadge) {
            analyticsDbBadge.textContent = data.database ? ('DB: ' + data.database) : 'DB: unknown';
        }

        const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];
        if (analyticsWarning) {
            if (warnings.length > 0) {
                analyticsWarning.classList.remove('hidden');
                analyticsWarning.textContent = warnings.join(' ');
            } else {
                analyticsWarning.classList.add('hidden');
                analyticsWarning.textContent = '';
            }
        }

        renderAnalyticsChart(
            monthlyRevenueCanvas,
            'monthlyRevenue',
            'line',
            (chartData.monthlyRevenue && chartData.monthlyRevenue.labels) || [],
            (chartData.monthlyRevenue && chartData.monthlyRevenue.values) || [],
            'Revenue',
            'rgba(59, 130, 246, 0.35)'
        );

        renderAnalyticsChart(
            orderStatusCanvas,
            'orderStatus',
            'doughnut',
            (chartData.orderStatus && chartData.orderStatus.labels) || [],
            (chartData.orderStatus && chartData.orderStatus.values) || [],
            'Orders',
            ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']
        );

        renderAnalyticsChart(
            topCategoriesCanvas,
            'topCategories',
            'bar',
            (chartData.topCategories && chartData.topCategories.labels) || [],
            (chartData.topCategories && chartData.topCategories.values) || [],
            'Sales',
            '#14b8a6'
        );

        renderAnalyticsChart(
            paymentMethodsCanvas,
            'paymentMethods',
            'pie',
            (chartData.paymentMethods && chartData.paymentMethods.labels) || [],
            (chartData.paymentMethods && chartData.paymentMethods.values) || [],
            'Payments',
            ['#6366f1', '#22c55e', '#f97316', '#e11d48', '#0ea5e9']
        );
    }

    function loadAnalytics() {
        axios.get(apiBase + '/analytics.php')
            .then(function(response) {
                renderAnalytics(response.data || {});
            })
            .catch(function(error) {
                console.error(error);
                if (analyticsWarning) {
                    analyticsWarning.classList.remove('hidden');
                    analyticsWarning.textContent = getApiErrorMessage(error, 'Unable to load analytics data.');
                }
                if (analyticsDbBadge) {
                    analyticsDbBadge.textContent = 'DB: unavailable';
                }
            });
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
