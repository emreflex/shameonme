import Alpine from "alpinejs";
import Chart from "chart.js/auto";
import {
    createIcons,
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    Bell,
    Calculator,
    CalendarDays,
    ChartNoAxesCombined,
    ChartPie,
    CircleAlert,
    CircleCheck,
    CirclePlus,
    ClipboardList,
    Clock3,
    CreditCard,
    DatabaseBackup,
    Download,
    FileCheck2,
    FileSpreadsheet,
    Fuel,
    History,
    Info,
    LayoutDashboard,
    LockKeyhole,
    LogOut,
    Menu,
    PanelLeftClose,
    Plus,
    Printer,
    Save,
    ScanLine,
    Settings2,
    ShieldCheck,
    ShoppingBasket,
    SlidersHorizontal,
    SunMoon,
    UsersRound,
    Wallet,
} from "lucide";
const icons = {
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    Bell,
    Calculator,
    CalendarDays,
    ChartNoAxesCombined,
    ChartPie,
    CircleAlert,
    CircleCheck,
    CirclePlus,
    ClipboardList,
    Clock3,
    CreditCard,
    DatabaseBackup,
    Download,
    FileCheck2,
    FileSpreadsheet,
    Fuel,
    History,
    Info,
    LayoutDashboard,
    LockKeyhole,
    LogOut,
    Menu,
    PanelLeftClose,
    Plus,
    Printer,
    Save,
    ScanLine,
    Settings2,
    ShieldCheck,
    ShoppingBasket,
    SlidersHorizontal,
    SunMoon,
    UsersRound,
    Wallet,
};
window.Alpine = Alpine;
Alpine.data("shiftForm", (amounts, codes, department) => ({
    amounts,
    codes,
    department,
    get code() {
        return this.codes[this.department] || "petrol";
    },
    cents(v) {
        if (v === null || v === "") return 0;
        const s = String(v);
        if (!/^\d{1,11}(\.\d{1,2})?$/.test(s)) return NaN;
        const [a, b = ""] = s.split(".");
        return Number(a) * 100 + Number(b.padEnd(2, "0"));
    },
    get result() {
        const a = {};
        for (const [k, v] of Object.entries(this.amounts)) a[k] = this.cents(v);
        const pos = a.pos_kuveyt + a.pos_garanti + a.pos_ziraat + a.pos_other;
        const expected =
            a.sales -
            a.fast -
            a.expense -
            pos -
            (this.code === "petrol" ? a.credit + a.points : 0);
        const counted = a.counted_cash + a.coins;
        return {
            pos,
            expected,
            counted,
            diff:
                this.amounts.counted_cash === null ||
                this.amounts.counted_cash === ""
                    ? null
                    : counted - expected,
        };
    },
    format(c) {
        return Number.isFinite(c)
            ? new Intl.NumberFormat("tr-TR", {
                  style: "currency",
                  currency: "TRY",
              }).format(c / 100)
            : "Geçersiz tutar";
    },
}));
Alpine.start();
createIcons({ icons });
for (const canvas of document.querySelectorAll("canvas[data-chart]")) {
    const values = JSON.parse(canvas.dataset.values || "{}");
    const labels = Object.keys(values);
    const empty = !labels.length || !Object.values(values).some(Number);
    if (empty) {
        const div = document.createElement("div");
        div.className = "chart-empty";
        div.textContent = "Bu dönemde grafik için veri bulunmuyor.";
        canvas.parentElement.append(div);
        canvas.style.display = "none";
        continue;
    }
    const type = canvas.dataset.chart;
    const count = canvas.dataset.unit === "count";
    const cashLabels = {
        short: "Eksik",
        over: "Fazla",
        balanced: "Dengeli",
        waiting: "Sayım bekliyor",
    };
    new Chart(canvas, {
        type,
        data: {
            labels: count ? labels.map((l) => cashLabels[l] || l) : labels,
            datasets: [
                {
                    label: count ? "Vardiya sayısı" : "Tutar (₺)",
                    data: Object.values(values).map((c) =>
                        count ? c : c / 100,
                    ),
                    borderColor: type === "doughnut" ? undefined : "#119c8e",
                    backgroundColor:
                        type === "doughnut"
                            ? ["#376cea", "#12aa89", "#edb64a", "#8999ac"]
                            : type === "line"
                              ? "rgba(17,156,142,.08)"
                              : "#119c8e",
                    borderWidth: type === "doughnut" ? 4 : 2,
                    fill: true,
                    tension: 0.35,
                    borderRadius: 5,
                    pointRadius: 3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: "73%",
            plugins: {
                legend: {
                    display: type === "doughnut",
                    position: "bottom",
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: { size: 11 },
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (c) =>
                            count
                                ? String(c.parsed.y ?? c.parsed) + " vardiya"
                                : new Intl.NumberFormat("tr-TR", {
                                      style: "currency",
                                      currency: "TRY",
                                  }).format(c.parsed.y ?? c.parsed),
                    },
                },
            },
            scales:
                type === "doughnut"
                    ? {}
                    : {
                          x: {
                              grid: { display: false },
                              ticks: { font: { size: 10 } },
                          },
                          y: {
                              beginAtZero: true,
                              grid: { color: "rgba(140,160,170,.12)" },
                              border: { display: false },
                              ticks: {
                                  font: { size: 10 },
                                  callback: (v) =>
                                      new Intl.NumberFormat("tr-TR", {
                                          notation: "compact",
                                      }).format(v),
                              },
                          },
                      },
        },
    });
}
for (const form of document.querySelectorAll("form"))
    form.addEventListener("submit", () => {
        const button = form.querySelector(
            'button[type="submit"],button:not([type])',
        );
        if (button) {
            button.setAttribute("aria-busy", "true");
            setTimeout(() => button.removeAttribute("aria-busy"), 5000);
        }
    });
