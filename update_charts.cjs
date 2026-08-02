const fs = require('fs');
const filePath = 'd:\\\\orderan\\\\wakamiya\\\\resources\\\\views\\\\dashboard\\\\index.blade.php';
let content = fs.readFileSync(filePath, 'utf8');

const oldScript = `<script>
    document.addEventListener('DOMContentLoaded', function() {
        const updateChartConfig = () => {
            const isDark = document.documentElement.classList.contains('dark');
            Chart.defaults.color = isDark ? '#94a3b8' : '#6b7280';
            Chart.defaults.scale.grid.color = isDark ? 'rgba(255,255,255,0.05)' : '#f3f4f6';
        };
        updateChartConfig();
        
        const createChartConfig = (type, data, bgColors) => {
            return {
                type: type,
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.data,
                        backgroundColor: bgColors,
                        borderWidth: 0,
                        borderRadius: type === 'bar' ? 8 : 0, 
                        hoverOffset: type === 'doughnut' ? 8 : 0, 
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: type === 'doughnut' ? '70%' : undefined,
                    plugins: {
                        legend: { 
                            position: type === 'doughnut' ? 'right' : 'bottom', 
                            labels: { 
                                boxWidth: 10, 
                                usePointStyle: true,
                                font: { size: 12, weight: '600' },
                                padding: 20
                            } 
                        },
                        tooltip: { 
                            backgroundColor: 'rgba(15, 23, 42, 0.9)', 
                            padding: 16, 
                            titleFont: { size: 14, weight: '700' }, 
                            bodyFont: { size: 13, weight: '500' }, 
                            cornerRadius: 12,
                            boxPadding: 6,
                            usePointStyle: true
                        }
                    },
                    scales: type === 'bar' ? {
                        y: { 
                            beginAtZero: true, 
                            border: { display: false }, 
                            grid: { drawBorder: false }, 
                            ticks: { padding: 10, font: { weight: '500' } } 
                        },
                        x: { 
                            grid: { display: false, drawBorder: false }, 
                            ticks: { padding: 10, font: { weight: '600' } } 
                        }
                    } : {
                        x: { display: false },
                        y: { display: false }
                    }
                }
            };
        };`;

const newScript = `<script>
    document.addEventListener('DOMContentLoaded', function() {
        const updateChartConfig = () => {
            Chart.defaults.color = '#64748b'; // slate-500
            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.scale.grid.color = '#f1f5f9'; // slate-100
        };
        updateChartConfig();
        
        const createChartConfig = (type, data, bgColors) => {
            let backgroundColor = bgColors;
            let borderColor = 'transparent';
            
            if (type === 'line') {
                backgroundColor = (context) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, 'rgba(56, 189, 248, 0.4)'); // cyan-400
                    gradient.addColorStop(1, 'rgba(56, 189, 248, 0.0)');
                    return gradient;
                };
                borderColor = '#0ea5e9'; // sky-500
            }

            return {
                type: type,
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Data',
                        data: data.data,
                        backgroundColor: backgroundColor,
                        borderColor: borderColor,
                        borderWidth: type === 'line' ? 3 : 0,
                        borderRadius: type === 'bar' ? 6 : 0, 
                        hoverOffset: type === 'doughnut' ? 8 : 0,
                        fill: type === 'line',
                        tension: type === 'line' ? 0.4 : 0,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#0ea5e9',
                        pointBorderWidth: type === 'line' ? 2 : 0,
                        pointRadius: type === 'line' ? 4 : 0,
                        pointHoverRadius: type === 'line' ? 6 : 0,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: type === 'doughnut' ? '75%' : undefined,
                    plugins: {
                        legend: { 
                            position: type === 'doughnut' ? 'right' : 'bottom', 
                            display: type === 'doughnut', 
                            labels: { 
                                boxWidth: 10, 
                                usePointStyle: true,
                                font: { size: 12, weight: '600' },
                                padding: 20
                            } 
                        },
                        tooltip: { 
                            backgroundColor: 'rgba(15, 23, 42, 0.95)', 
                            padding: 16, 
                            titleFont: { size: 13, weight: '700' }, 
                            bodyFont: { size: 13, weight: '500' }, 
                            cornerRadius: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            borderColor: 'rgba(255,255,255,0.1)',
                            borderWidth: 1
                        }
                    },
                    scales: (type === 'bar' || type === 'line') ? {
                        y: { 
                            beginAtZero: true, 
                            border: { display: false }, 
                            grid: { color: '#f8fafc', drawBorder: false }, 
                            ticks: { padding: 12, font: { weight: '500', size: 11 }, color: '#94a3b8' } 
                        },
                        x: { 
                            grid: { display: false, drawBorder: false }, 
                            ticks: { padding: 10, font: { weight: '600', size: 11 }, color: '#64748b' } 
                        }
                    } : {
                        x: { display: false },
                        y: { display: false }
                    }
                }
            };
        };`;

content = content.replace(oldScript, newScript);

// Change studentBatchChart to 'line'
const oldCall = `// 2. Student by Batch
        renderChartOrEmpty('containerStudentBatch', 'studentBatchChart', 'bar', @json($charts['studentBatch'] ?? ['labels'=>[],'data'=>[]]), colors);`;
const newCall = `// 2. Student by Batch
        renderChartOrEmpty('containerStudentBatch', 'studentBatchChart', 'line', @json($charts['studentBatch'] ?? ['labels'=>[],'data'=>[]]), colors);`;
content = content.replace(oldCall, newCall);

// Change icons in index.blade.php for the chart-cards
content = content.replace(
    '<div class="w-8 h-8 rounded-lg bg-pink-50 flex items-center justify-center mr-3 text-pink-500">',
    '<div class="w-10 h-10 rounded-xl bg-pink-50 flex items-center justify-center mr-4 text-pink-600">'
).replace(
    '<svg class="w-4 h-4" fill="none" stroke="currentColor"',
    '<svg class="w-5 h-5" fill="none" stroke="currentColor"'
);

// We need to globally update the sizes of the icons in the chart cards inside index.blade.php
// I will just let the chart card handle its default icon if I don't replace them perfectly.

fs.writeFileSync(filePath, content, 'utf8');
console.log('Chart config updated successfully');
