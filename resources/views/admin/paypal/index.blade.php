@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">PayPal Subscriptions</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Active Subscriptions</span>
                                    <span class="info-box-number" id="active-count">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-money-bill-wave"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Monthly Revenue</span>
                                    <span class="info-box-number" id="mrr">$0.00</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i class="fas fa-pause-circle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Suspended</span>
                                    <span class="info-box-number" id="suspended-count">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-danger"><i class="fas fa-ban"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Cancelled</span>
                                    <span class="info-box-number" id="cancelled-count">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Subscription List</h3>
                                    <div class="card-tools">
                                        <div class="input-group input-group-sm" style="width: 250px;">
                                            <input type="text" name="table_search" id="search-input" class="form-control float-right" placeholder="Search">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-default" id="search-btn">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-hover text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Customer</th>
                                                <th>Plan</th>
                                                <th>Status</th>
                                                <th>Start Date</th>
                                                <th>Next Billing</th>
                                                <th>Amount</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="subscriptions-table-body">
                                            <!-- Subscription data will be loaded here via JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="card-footer clearfix">
                                    <div class="float-right">
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination" id="pagination">
                                                <!-- Pagination will be added here -->
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subscription Details Modal -->
<div class="modal fade" id="subscriptionModal" tabindex="-1" role="dialog" aria-labelledby="subscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subscriptionModalLabel">Subscription Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="subscription-details">
                <!-- Subscription details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Load stats
    function loadStats() {
        $.get('{{ route("admin.paypal.stats") }}')
            .done(function(response) {
                if (response.success) {
                    $('#active-count').text(response.data.total_active);
                    $('#suspended-count').text(response.data.total_suspended);
                    $('#cancelled-count').text(response.data.total_cancelled);
                    $('#mrr').text('$' + response.data.mrr);
                }
            });
    }

    // Load subscriptions
    function loadSubscriptions(page = 1, search = '') {
        let url = `{{ route('admin.paypal.subscriptions') }}?page=${page}`;
        
        if (search) {
            url += `&search=${encodeURIComponent(search)}`;
        }

        $.get(url)
            .done(function(response) {
                if (response.success) {
                    const subscriptions = response.data.subscriptions || [];
                    const tbody = $('#subscriptions-table-body');
                    tbody.empty();

                    if (subscriptions.length === 0) {
                        tbody.append('<tr><td colspan="8" class="text-center">No subscriptions found</td></tr>');
                        return;
                    }

                    subscriptions.forEach(function(sub) {
                        const customerName = sub.subscriber?.name?.given_name + ' ' + (sub.subscriber?.name?.surname || '');
                        const customerEmail = sub.subscriber?.email_address || 'N/A';
                        const planName = sub.plan_id || 'N/A';
                        const startDate = sub.start_time ? new Date(sub.start_time).toLocaleDateString() : 'N/A';
                        const nextBilling = sub.billing_info?.next_billing_time ? new Date(sub.billing_info.next_billing_time).toLocaleDateString() : 'N/A';
                        const amount = sub.billing_info?.last_payment?.amount?.value 
                            ? `${sub.billing_info.last_payment.amount.value} ${sub.billing_info.last_payment.amount.currency_code}` 
                            : 'N/A';
                        
                        const statusBadge = getStatusBadge(sub.status);

                        const row = `
                            <tr>
                                <td>${sub.id || 'N/A'}</td>
                                <td>
                                    <div>${customerName.trim() || 'N/A'}</div>
                                    <small class="text-muted">${customerEmail}</small>
                                </td>
                                <td>${planName}</td>
                                <td>${statusBadge}</td>
                                <td>${startDate}</td>
                                <td>${nextBilling}</td>
                                <td>${amount}</td>
                                <td>
                                    <button class="btn btn-sm btn-info view-subscription" data-id="${sub.id}">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.append(row);
                    });

                    // Update pagination
                    updatePagination(response.data);
                }
            });
    }

    // Get status badge HTML
    function getStatusBadge(status) {
        const statusMap = {
            'ACTIVE': 'success',
            'APPROVAL_PENDING': 'warning',
            'APPROVED': 'info',
            'SUSPENDED': 'warning',
            'CANCELLED': 'danger',
            'EXPIRED': 'secondary'
        };

        const statusText = status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
        return `<span class="badge bg-${statusMap[status] || 'secondary'}">${statusText}</span>`;
    }

    // Update pagination
    function updatePagination(data) {
        const pagination = $('#pagination');
        pagination.empty();

        if (!data.pages || data.pages <= 1) return;

        // Previous button
        const prevDisabled = data.page === 1 ? 'disabled' : '';
        pagination.append(`
            <li class="page-item ${prevDisabled}">
                <a class="page-link" href="#" data-page="${data.page - 1}" ${prevDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}>
                    &laquo;
                </a>
            </li>
        `);

        // Page numbers
        for (let i = 1; i <= data.pages; i++) {
            const active = i === data.page ? 'active' : '';
            pagination.append(`
                <li class="page-item ${active}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        // Next button
        const nextDisabled = data.page >= data.pages ? 'disabled' : '';
        pagination.append(`
            <li class="page-item ${nextDisabled}">
                <a class="page-link" href="#" data-page="${data.page + 1}" ${nextDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}>
                    &raquo;
                </a>
            </li>
        `);
    }

    // View subscription details
    $(document).on('click', '.view-subscription', function() {
        const subscriptionId = $(this).data('id');
        
        $.get(`/admin/paypal/subscriptions/${subscriptionId}`)
            .done(function(response) {
                if (response.success) {
                    const sub = response.data;
                    const customerName = sub.subscriber?.name?.given_name + ' ' + (sub.subscriber?.name?.surname || '');
                    const customerEmail = sub.subscriber?.email_address || 'N/A';
                    const planName = sub.plan_id || 'N/A';
                    const startDate = sub.start_time ? new Date(sub.start_time).toLocaleString() : 'N/A';
                    const nextBilling = sub.billing_info?.next_billing_time ? new Date(sub.billing_info.next_billing_time).toLocaleString() : 'N/A';
                    const amount = sub.billing_info?.last_payment?.amount 
                        ? `${sub.billing_info.last_payment.amount.value} ${sub.billing_info.last_payment.amount.currency_code}` 
                        : 'N/A';
                    
                    const statusBadge = getStatusBadge(sub.status);
                    
                    let detailsHtml = `
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Subscription Details</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>ID:</th>
                                        <td>${sub.id || 'N/A'}</td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>${statusBadge}</td>
                                    </tr>
                                    <tr>
                                        <th>Plan:</th>
                                        <td>${planName}</td>
                                    </tr>
                                    <tr>
                                        <th>Start Date:</th>
                                        <td>${startDate}</td>
                                    </tr>
                                    <tr>
                                        <th>Next Billing:</th>
                                        <td>${nextBilling}</td>
                                    </tr>
                                    <tr>
                                        <th>Amount:</th>
                                        <td>${amount}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Customer Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Name:</th>
                                        <td>${customerName.trim() || 'N/A'}</td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td>${customerEmail}</td>
                                    </tr>
                                    <tr>
                                        <th>Payer ID:</th>
                                        <td>${sub.subscriber?.payer_id || 'N/A'}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <h5>Billing Information</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Transaction ID</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                    `;
                    
                    // Add payment history if available
                    if (sub.billing_info?.cycle_executions && sub.billing_info.cycle_executions.length > 0) {
                        sub.billing_info.cycle_executions.forEach(cycle => {
                            const cycleDate = cycle.time ? new Date(cycle.time).toLocaleString() : 'N/A';
                            detailsHtml += `
                                <tr>
                                    <td>${cycleDate}</td>
                                    <td>${amount}</td>
                                    <td>${cycle.tenure_type || 'N/A'}</td>
                                    <td>${cycle.cycles_completed || 'N/A'}/${cycle.cycles_remaining || '0'}</td>
                                </tr>
                            `;
                        });
                    } else {
                        detailsHtml += `
                            <tr>
                                <td colspan="4" class="text-center">No payment history available</td>
                            </tr>
                        `;
                    }
                    
                    detailsHtml += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    $('#subscription-details').html(detailsHtml);
                    $('#subscriptionModal').modal('show');
                }
            });
    });

    // Handle pagination clicks
    $(document).on('click', '.page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) {
            const search = $('#search-input').val();
            loadSubscriptions(page, search);
        }
    });

    // Handle search
    $('#search-btn').on('click', function(e) {
        e.preventDefault();
        const search = $('#search-input').val();
        loadSubscriptions(1, search);
    });

    // Handle Enter key in search input
    $('#search-input').on('keyup', function(e) {
        if (e.key === 'Enter') {
            const search = $(this).val();
            loadSubscriptions(1, search);
        }
    });

    // Initial load
    loadStats();
    loadSubscriptions();
});
</script>
@endpush
