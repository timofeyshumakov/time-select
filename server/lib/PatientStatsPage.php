<?php

declare(strict_types=1);

class PatientStatsPage
{
    public function show(callable $loadStats): void
    {
        $patientId = $_GET['patient_id'] ?? null;
        $forceRefresh = isset($_GET['refresh']);

        if (!$patientId) {
            $this->renderPatientIdForm();
            return;
        }

        try {
            $payload = $loadStats((int)$patientId, $forceRefresh);
            $this->renderPatientStatsPage(
                (int)$patientId,
                $payload['stats'],
                $payload['patientInfo'],
                $payload['bitrixContact']
            );
        } catch (Exception $e) {
            $this->renderErrorPage($e);
        }
    }

    private function renderPatientIdForm(): void
    {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Статистика пациента Renovatio</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background-color: #f8f9fa; }
                .container { max-width: 600px; margin-top: 100px; }
                .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Статистика пациента Renovatio</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="mb-3">
                                <label for="patient_id" class="form-label">ID пациента в Renovatio:</label>
                                <input type="number"
                                       class="form-control form-control-lg"
                                       id="patient_id"
                                       name="patient_id"
                                       required
                                       placeholder="Например: 12345"
                                       autofocus>
                                <div class="form-text">Введите ID пациента из системы Renovatio</div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100">Показать статистику</button>
                        </form>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    private function renderPatientStatsPage(int $patientId, array $stats, ?array $patientInfo, ?array $bitrixContact): void
    {
        $statistics = $stats['statistics'];
        $monthlyBreakdown = $stats['monthly_breakdown'];
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Пациент #<?= htmlspecialchars((string)$patientId) ?> - Статистика</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <style>
                body { background-color: #f8f9fa; }
                .container { max-width: 1400px; margin-top: 20px; }
                .stat-card {
                    background: white;
                    border-radius: 10px;
                    padding: 20px;
                    margin-bottom: 20px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .stat-value { font-size: 32px; font-weight: bold; color: #0d6efd; }
                .stat-label { color: #6c757d; font-size: 14px; margin-bottom: 5px; }
                .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
                .status-completed { background: #d4edda; color: #155724; }
                .status-upcoming { background: #fff3cd; color: #856404; }
                .status-cancelled { background: #f8d7da; color: #721c24; }
                .chart-container { position: relative; height: 300px; margin-bottom: 30px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>
                        Статистика пациента #<?= htmlspecialchars((string)$patientId) ?>
                        <?php if ($patientInfo): ?>
                            <small class="text-muted fs-5">
                                <?= htmlspecialchars($patientInfo['last_name'] ?? '') ?>
                                <?= htmlspecialchars($patientInfo['first_name'] ?? '') ?>
                            </small>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <a href="?patient_id=<?= $patientId ?>&refresh=1" class="btn btn-warning me-2">Обновить данные</a>
                        <a href="?" class="btn btn-secondary">Назад</a>
                    </div>
                </div>

                <div class="alert alert-info">
                    <strong>Данные сгенерированы:</strong> <?= htmlspecialchars($stats['generated_at']) ?><br>
                    <strong>Период данных:</strong> <?= htmlspecialchars($stats['data_period']['from']) ?> - <?= htmlspecialchars($stats['data_period']['to']) ?><br>
                    <strong>Обработано месяцев:</strong> <?= $stats['months_processed'] ?><br>
                    <strong>Контакт в Битрикс:</strong>
                    <?php if ($bitrixContact): ?>
                        ID: <?= $bitrixContact['ID'] ?> -
                        Текущее кол-во визитов (завершённых): <?= (int)($bitrixContact['UF_CRM_1776436729'] ?? 0) ?>,
                        Текущая сумма: <?= number_format((float)($bitrixContact['UF_CRM_1776436700'] ?? 0), 2, '.', ' ') ?> ₽
                    <?php else: ?>
                        <span class="text-warning">Не найден</span>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-label">Всего визитов</div>
                            <div class="stat-value"><?= $statistics['total_visits'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-label">Завершенных</div>
                            <div class="stat-value text-success"><?= $statistics['completed'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-label">Общая сумма</div>
                            <div class="stat-value text-primary"><?= number_format($statistics['total_paid'], 0, '.', ' ') ?> ₽</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-label">Средний чек</div>
                            <div class="stat-value text-info"><?= number_format($statistics['average_check'], 0, '.', ' ') ?> ₽</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <h4>Динамика по месяцам</h4>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <div class="stat-card">
                    <h4>Разбивка по месяцам</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Месяц</th>
                                    <th>Всего визитов</th>
                                    <th>Завершено</th>
                                    <th>Отменено</th>
                                    <th>Предстоящие</th>
                                    <th>Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthlyBreakdown as $month): ?>
                                <tr>
                                    <td><?= htmlspecialchars($month['month']) ?></td>
                                    <td><?= $month['total_visits'] ?></td>
                                    <td class="text-success"><?= $month['completed'] ?></td>
                                    <td class="text-danger"><?= $month['cancelled'] ?></td>
                                    <td class="text-warning"><?= $month['upcoming'] ?></td>
                                    <td><?= number_format($month['total_sum'], 0, '.', ' ') ?> ₽</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($statistics['top_services'])): ?>
                <div class="stat-card">
                    <h4>Топ услуг</h4>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Услуга</th>
                                    <th>Количество</th>
                                    <th>Общая сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statistics['top_services'] as $serviceName => $data): ?>
                                <tr>
                                    <td><?= htmlspecialchars($serviceName) ?></td>
                                    <td><?= $data['count'] ?></td>
                                    <td><?= number_format($data['total'], 0, '.', ' ') ?> ₽</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <div class="stat-card">
                    <h4>Все визиты</h4>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>id</th>
                                    <th>Дата</th>
                                    <th>Клиника</th>
                                    <th>Врач</th>
                                    <th>Статус</th>
                                    <th>Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['visits'] as $visit): ?>
                                <tr>
                                    <td><?= htmlspecialchars($visit['id'] ?? 'Н/Д') ?></td>
                                    <td><?= htmlspecialchars($visit['time_start'] ?? 'Н/Д') ?></td>
                                    <td><?= htmlspecialchars($visit['clinic_title'] ?? 'Н/Д') ?></td>
                                    <td><?= htmlspecialchars($visit['doctor_name'] ?? 'Н/Д') ?></td>
                                    <td>
                                        <?php
                                        $status = $visit['status'] ?? 'unknown';
                                        $statusClass = match ($status) {
                                            'completed' => 'status-completed',
                                            'upcoming' => 'status-upcoming',
                                            'refused', 'cancelled' => 'status-cancelled',
                                            default => ''
                                        };
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($visit['sum_value'] ?? 0, 0, '.', ' ') ?> ₽</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($stats['errors'])): ?>
                <div class="alert alert-warning">
                    <h5>Ошибки при загрузке:</h5>
                    <ul>
                        <?php foreach ($stats['errors'] as $error): ?>
                        <li><?= htmlspecialchars($error['period']) ?>: <?= htmlspecialchars($error['error']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

            <script>
                const ctx = document.getElementById('monthlyChart').getContext('2d');
                const monthlyData = <?= json_encode($monthlyBreakdown) ?>;
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: monthlyData.map(m => m.month),
                        datasets: [{
                            label: 'Сумма (₽)',
                            data: monthlyData.map(m => m.sum_value),
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            yAxisID: 'y',
                            tension: 0.1
                        }, {
                            label: 'Количество визитов',
                            data: monthlyData.map(m => m.completed),
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.1)',
                            yAxisID: 'y1',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: { display: true, text: 'Сумма (₽)' }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: { display: true, text: 'Количество визитов' },
                                grid: { drawOnChartArea: false }
                            }
                        }
                    }
                });
            </script>
        </body>
        </html>
        <?php
    }

    private function renderErrorPage(Exception $e): void
    {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Ошибка</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="alert alert-danger">
                    <h4>Произошла ошибка</h4>
                    <p><?= htmlspecialchars($e->getMessage()) ?></p>
                    <hr>
                    <pre class="small"><?= htmlspecialchars($e->getTraceAsString()) ?></pre>
                </div>
                <a href="?" class="btn btn-primary">Назад</a>
            </div>
        </body>
        </html>
        <?php
    }
}
