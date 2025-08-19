<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Customers - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8" x-data="stripeCustomers()">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Customers de Stripe</h1>
            
            <!-- Botones de acción -->
            <div class="flex flex-wrap gap-4 mb-6">
                <button 
                    @click="loadCustomers()" 
                    :disabled="loading"
                    class="bg-blue-500 hover:bg-blue-600 disabled:bg-blue-300 text-white px-4 py-2 rounded-lg font-medium transition-colors"
                >
                    <span x-show="!loading">Cargar Customers (Paginado)</span>
                    <span x-show="loading">Cargando...</span>
                </button>
                
                <button 
                    @click="loadAllCustomers()" 
                    :disabled="loading"
                    class="bg-green-500 hover:bg-green-600 disabled:bg-green-300 text-white px-4 py-2 rounded-lg font-medium transition-colors"
                >
                    <span x-show="!loading">Cargar Todos los Customers</span>
                    <span x-show="loading">Cargando...</span>
                </button>
                
                <button 
                    @click="showSearchForm = !showSearchForm" 
                    class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg font-medium transition-colors"
                >
                    Buscar por Email
                </button>
            </div>

            <!-- Formulario de búsqueda -->
            <div x-show="showSearchForm" class="mb-6 p-4 bg-gray-50 rounded-lg">
                <div class="flex gap-4">
                    <input 
                        type="email" 
                        x-model="searchEmail" 
                        placeholder="Ingresa el email del customer"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    <button 
                        @click="searchCustomers()" 
                        :disabled="loading || !searchEmail"
                        class="bg-purple-500 hover:bg-purple-600 disabled:bg-purple-300 text-white px-4 py-2 rounded-lg font-medium transition-colors"
                    >
                        Buscar
                    </button>
                </div>
            </div>

            <!-- Información de estado -->
            <div class="mb-4">
                <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <p><strong>Error:</strong> <span x-text="error"></span></p>
                </div>
                
                <div x-show="customers.length > 0" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
                    <p><strong>Total de customers encontrados:</strong> <span x-text="customers.length"></span></p>
                    <p x-show="hasMore"><strong>Nota:</strong> Hay más customers disponibles. Usa la paginación para ver más.</p>
                </div>
            </div>

            <!-- Tabla de customers -->
            <div x-show="customers.length > 0" class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha de Creación</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="customer in customers" :key="customer.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="customer.id"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="customer.email"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="customer.name || 'N/A'"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="formatDate(customer.created)"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button 
                                        @click="viewCustomer(customer.id)"
                                        class="text-blue-600 hover:text-blue-900"
                                    >
                                        Ver Detalles
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div x-show="hasMore && customers.length > 0" class="mt-6 flex justify-center">
                <button 
                    @click="loadMoreCustomers()" 
                    :disabled="loading"
                    class="bg-blue-500 hover:bg-blue-600 disabled:bg-blue-300 text-white px-6 py-2 rounded-lg font-medium transition-colors"
                >
                    <span x-show="!loading">Cargar Más</span>
                    <span x-show="loading">Cargando...</span>
                </button>
            </div>

            <!-- Modal para detalles del customer -->
            <div x-show="showModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" x-transition>
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Detalles del Customer</h3>
                        <div x-show="selectedCustomer" class="space-y-3">
                            <div><strong>ID:</strong> <span x-text="selectedCustomer?.id"></span></div>
                            <div><strong>Email:</strong> <span x-text="selectedCustomer?.email"></span></div>
                            <div><strong>Nombre:</strong> <span x-text="selectedCustomer?.name || 'N/A'"></span></div>
                            <div><strong>Descripción:</strong> <span x-text="selectedCustomer?.description || 'N/A'"></span></div>
                            <div><strong>Fecha de Creación:</strong> <span x-text="formatDate(selectedCustomer?.created)"></span></div>
                        </div>
                        <div class="flex justify-end mt-6">
                            <button 
                                @click="showModal = false" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function stripeCustomers() {
            return {
                customers: [],
                loading: false,
                error: null,
                hasMore: false,
                lastCustomerId: null,
                showSearchForm: false,
                searchEmail: '',
                showModal: false,
                selectedCustomer: null,

                async loadCustomers() {
                    this.loading = true;
                    this.error = null;
                    
                    try {
                        const response = await fetch('/stripe/customers');
                        const result = await response.json();
                        
                        if (result.success) {
                            this.customers = result.data;
                            this.hasMore = result.has_more;
                            if (this.customers.length > 0) {
                                this.lastCustomerId = this.customers[this.customers.length - 1].id;
                            }
                        } else {
                            this.error = result.error;
                        }
                    } catch (error) {
                        this.error = 'Error al cargar los customers: ' + error.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async loadAllCustomers() {
                    this.loading = true;
                    this.error = null;
                    
                    try {
                        const response = await fetch('/stripe/customers/all');
                        const result = await response.json();
                        
                        if (result.success) {
                            this.customers = result.data;
                            this.hasMore = false;
                        } else {
                            this.error = result.error;
                        }
                    } catch (error) {
                        this.error = 'Error al cargar todos los customers: ' + error.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async loadMoreCustomers() {
                    if (!this.lastCustomerId) return;
                    
                    this.loading = true;
                    this.error = null;
                    
                    try {
                        const response = await fetch(`/stripe/customers?starting_after=${this.lastCustomerId}`);
                        const result = await response.json();
                        
                        if (result.success) {
                            this.customers = [...this.customers, ...result.data];
                            this.hasMore = result.has_more;
                            if (result.data.length > 0) {
                                this.lastCustomerId = result.data[result.data.length - 1].id;
                            }
                        } else {
                            this.error = result.error;
                        }
                    } catch (error) {
                        this.error = 'Error al cargar más customers: ' + error.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async searchCustomers() {
                    if (!this.searchEmail) return;
                    
                    this.loading = true;
                    this.error = null;
                    
                    try {
                        const response = await fetch(`/stripe/customers/search?email=${encodeURIComponent(this.searchEmail)}`);
                        const result = await response.json();
                        
                        if (result.success) {
                            this.customers = result.data;
                            this.hasMore = false;
                        } else {
                            this.error = result.error;
                        }
                    } catch (error) {
                        this.error = 'Error al buscar customers: ' + error.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async viewCustomer(customerId) {
                    try {
                        const response = await fetch(`/stripe/customers/${customerId}`);
                        const result = await response.json();
                        
                        if (result.success) {
                            this.selectedCustomer = result.data;
                            this.showModal = true;
                        } else {
                            this.error = result.error;
                        }
                    } catch (error) {
                        this.error = 'Error al cargar el customer: ' + error.message;
                    }
                },

                formatDate(timestamp) {
                    return new Date(timestamp * 1000).toLocaleDateString('es-ES', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            }
        }
    </script>
</body>
</html>
