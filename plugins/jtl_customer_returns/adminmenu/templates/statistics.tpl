<div class="container-fluid">
    <h1><i class="fa fa-chart-bar"></i> Retouren-Statistiken</h1>
    
    <!-- Zeitraum-Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="form-inline">
                <input type="hidden" name="action" value="statistics">
                
                <div class="form-group mr-3">
                    <label class="mr-2">Zeitraum:</label>
                    <select name="period" class="form-control" onchange="this.form.submit()">
                        <option value="7days" {if $period === '7days'}selected{/if}>Letzte 7 Tage</option>
                        <option value="30days" {if $period === '30days'}selected{/if}>Letzte 30 Tage</option>
                        <option value="90days" {if $period === '90days'}selected{/if}>Letzte 90 Tage</option>
                        <option value="1year" {if $period === '1year'}selected{/if}>Letztes Jahr</option>
                    </select>
                </div>
                
                <span class="text-muted">
                    {$dateFrom|date_format:'%d.%m.%Y'} - {$dateTo|date_format:'%d.%m.%Y'}
                </span>
            </form>
        </div>
    </div>
    
    <!-- KPI-Karten -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h6>Retouren gesamt</h6>
                    <h2>{$stats.total_rmas}</h2>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h6>Artikel gesamt</h6>
                    <h2>{$stats.total_items}</h2>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h6>Rückerstattung gesamt</h6>
                    <h2>{$stats.total_refund|number_format:0:',':'.'} €</h2>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h6>Ø Bearbeitungszeit</h6>
                    <h2>{$stats.avg_processing_time} Tage</h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row 1 -->
    <div class="row mb-4">
        <!-- Status-Verteilung -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Status-Verteilung</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Timeline -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Retouren-Verlauf (Letzte 30 Tage)</h5>
                </div>
                <div class="card-body">
                    <canvas id="timelineChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Retourengründe -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Retourengründe</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Grund</th>
                                <th width="150">Anzahl</th>
                                <th width="200">Anteil</th>
                            </tr>
                        </thead>
                        <tbody>
                            {assign var="totalReasons" value=0}
                            {foreach $topReasons as $reason}
                                {assign var="totalReasons" value=$totalReasons+$reason.count}
                            {/foreach}
                            
                            {foreach $topReasons as $index => $reason}
                            {assign var="percentage" value=($reason.count / $totalReasons * 100)|round:1}
                            <tr>
                                <td>{$index+1}</td>
                                <td>{$reason.reason|escape:'html'}</td>
                                <td>{$reason.count}</td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" 
                                             role="progressbar" 
                                             style="width: {$percentage}%"
                                             aria-valuenow="{$percentage}" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            {$percentage}%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
// Status-Verteilung (Doughnut Chart)
var statusCtx = document.getElementById('statusChart').getContext('2d');
var statusChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: [
            {foreach $statusDistribution as $item}'{$item.status}'{if !$item@last},{/if}{/foreach}
        ],
        datasets: [{
            data: [
                {foreach $statusDistribution as $item}{$item.count}{if !$item@last},{/if}{/foreach}
            ],
            backgroundColor: [
                '#ffc107', // Offen (warning)
                '#17a2b8', // In Bearbeitung (info)
                '#28a745', // Akzeptiert (success)
                '#6c757d', // Abgeschlossen (secondary)
                '#dc3545'  // Abgelehnt (danger)
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            }
        }
    }
});

// Timeline (Line Chart)
var timelineCtx = document.getElementById('timelineChart').getContext('2d');
var timelineChart = new Chart(timelineCtx, {
    type: 'line',
    data: {
        labels: [
            {foreach $timeline as $item}'{$item.date|date_format:'%d.%m'}'{if !$item@last},{/if}{/foreach}
        ],
        datasets: [{
            label: 'Retouren',
            data: [
                {foreach $timeline as $item}{$item.count}{if !$item@last},{/if}{/foreach}
            ],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>
