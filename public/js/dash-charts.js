// these is code of first 2 charts on dashboard index page

// Demo data (K units) – tweak to your needs
const categories = [
    "JAN/23",
    "FEB/23",
    "MAR/23",
    "APR/23",
    "MAY/23",
    "JUN/23",
    "JUL/23",
    "AUG/23",
    "SEP/23",
    "OCT/23",
    "NOV/23",
    "DEC/23",
];
const pbnPostCampaigns = [44, 55, 40, 68, 21, 46, 21, 41, 55, 24, 45, 56];
const pbnBlogrollCampaigns = [22, 10, 21, 39, 12, 21, 36, 20, 43, 21, 29, 20];
const pbnStickyCampaigns = [30, 52, 41, 25, 34, 26, 21, 36, 26, 24, 43, 40];

// small helper to add “K”
const kFmt = (val) => (val === 0 ? "0" : `${val}K`);

const options = {
    chart: {
        height: 340,
        type: "line",
        toolbar: {
            show: false,
        },
        foreColor: "#64748b", // slate-500
        fontFamily: "Outfit, sans-serif", // 👈 apply Outfit globally to chart
    },
    series: [
        {
            name: "PBN Posts",
            type: "column",
            data: pbnPostCampaigns,
            color: "#ff4a17", // orange-600
        },
        {
            name: "PBN Blogroll",
            type: "column",
            data: pbnBlogrollCampaigns,
            color: "#cbd5e1", // slate-300
        },
        {
            name: "PBN Sticky Posts",
            type: "line",
            data: pbnStickyCampaigns,
            color: "#ff4a17", // slate-400
        },
    ],
    stroke: {
        width: [0, 0, 3],
        curve: "smooth",
    },
    dataLabels: {
        enabled: false,
    },
    markers: {
        size: [0, 0, 3],
        strokeWidth: 0,
    },
    grid: {
        borderColor: "#e2e8f0", // slate-200
        strokeDashArray: 4,
        padding: {
            left: 8,
            right: 8,
        },
    },
    xaxis: {
        categories,
        axisBorder: {
            show: false,
        },
        axisTicks: {
            show: false,
        },
        labels: {
            minHeight: 22,
            style: {
                fontSize: "12px",
                fontFamily: "Outfit, sans-serif", // 👈 ensure x-axis uses Outfit
            },
        },
    },
    yaxis: {
        min: 0,
        max: 70,
        tickAmount: 7,
        labels: {
            style: {
                fontFamily: "Outfit, sans-serif", // 👈 ensure y-axis uses Outfit
            },
            formatter: (val) => kFmt(Math.round(val)),
        },
    },
    plotOptions: {
        bar: {
            columnWidth: "26%",
            borderRadius: 6,
        },
    },
    tooltip: {
        shared: true,
        intersect: false,
        style: {
            fontFamily: "Outfit, sans-serif", // 👈 Outfit in tooltip
        },
        y: {
            formatter: (val) => kFmt(val),
        },
    },
    legend: {
        show: false,
        fontFamily: "Outfit, sans-serif", // 👈 if you enable legend later
    },
    responsive: [
        {
            breakpoint: 1024,
            options: {
                plotOptions: {
                    bar: {
                        columnWidth: "32%",
                    },
                },
            },
        },
        {
            breakpoint: 640, // sm
            options: {
                yaxis: {
                    tickAmount: 5,
                    max: 60,
                },
                grid: {
                    padding: {
                        left: 0,
                        right: 0,
                    },
                },
                plotOptions: {
                    bar: {
                        columnWidth: "38%",
                        borderRadius: 5,
                    },
                },
                markers: {
                    size: [0, 0, 2.5],
                },
            },
        },
    ],
};

// document.addEventListener('DOMContentLoaded', () => {
//     const chart = new ApexCharts(document.querySelector("#pbncampaigns-data"), options);
//     chart.render();
// });

// ================== chart no 2

const totalArticles = 1000;
const usedArticles = 650;

const percentage = Math.round((usedArticles / totalArticles) * 100);
const remaining = totalArticles - usedArticles;

// Update text fields
document.getElementById("articlesPercent").textContent = percentage + "%";
document.getElementById("usedCount").textContent =
    usedArticles.toLocaleString();
document.getElementById("remainingCount").textContent =
    remaining.toLocaleString();
document.getElementById(
    "usageMsg"
).innerHTML = `You’ve used <strong>${percentage}%</strong> of your articles. <strong>${remaining}</strong> remain available.`;

// Chart
const options2 = {
    chart: {
        type: "radialBar",
        height: 220,
        toolbar: {
            show: false,
        },
        fontFamily: "Outfit, sans-serif",
    },
    series: [percentage],
    labels: ["Used"],
    colors: ["#ff4a17"],
    fill: {
        type: "gradient",
        gradient: {
            shade: "light",
            gradientToColors: ["#fa7e5c"],
            stops: [0, 100],
        },
    },
    plotOptions: {
        radialBar: {
            startAngle: -90,
            endAngle: 90,
            hollow: {
                size: "60%",
            },
            track: {
                background: "#e2e8f0",
                strokeWidth: "90%",
                margin: 4,
            },
            dataLabels: {
                name: {
                    show: false,
                },
                value: {
                    show: false,
                },
            },
        },
    },
    stroke: {
        lineCap: "round",
    },
    tooltip: {
        enabled: true,
        y: {
            formatter: (val) => `${val}%`,
        },
        style: {
            fontFamily: "Outfit, sans-serif",
        },
    },
};

document.addEventListener("DOMContentLoaded", () => {
    const chart = new ApexCharts(
        document.querySelector("#pbncampaigns-data"),
        options
    );
    chart.render();
    const chart1 = new ApexCharts(
        document.querySelector("#articlesChart"),
        options2
    );
    chart1.render();
});
