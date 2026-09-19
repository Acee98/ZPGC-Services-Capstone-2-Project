                <div class="dashboard-charts-grid dashboard-charts-static">
                    <div class="chart-card">
                        <div class="chart-card-header">
                            <h2>Tickets Report</h2>
                            <button class="perf-filter-btn" type="button" disabled title="Sample data only">This Week</button>
                        </div>
                        <div class="chart-card-body">
                            <canvas id="ticketsReportChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-card-header">
                            <h2>Tickets - Categories</h2>
                        </div>
                        <div class="chart-card-body">
                            <canvas id="ticketsCategoriesChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-card-header">
                            <h2>Customer Satisfaction</h2>
                        </div>
                        <div class="chart-card-body chart-card-body-split">
                            <ul class="chart-legend-list">
                                <li><span class="legend-dot" style="background:#7ED9A8"></span>5 &nbsp; Very Satisfied</li>
                                <li><span class="legend-dot" style="background:#2E8B8B"></span>4 &nbsp; Satisfied</li>
                                <li><span class="legend-dot" style="background:#5BC8E8"></span>3 &nbsp; Not Sure</li>
                                <li><span class="legend-dot" style="background:#F5A623"></span>2 &nbsp; Not Satisfied</li>
                                <li><span class="legend-dot" style="background:#D9435E"></span>1 &nbsp; Hate It</li>
                            </ul>
                            <div class="chart-canvas-wrap">
                                <canvas id="satisfactionChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-card-header">
                            <h2>Severity Level</h2>
                        </div>
                        <div class="chart-card-body">
                            <canvas id="severityChart"></canvas>
                        </div>
                    </div>
                </div>
