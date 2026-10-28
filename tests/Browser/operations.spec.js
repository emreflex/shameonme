import { test, expect } from "@playwright/test";
import fs from "node:fs";
const credentials = JSON.parse(
    fs.readFileSync("storage/framework/browser-credentials.json", "utf8"),
);
test("giriş, petrol/market canlı hesap, kayıt, onay, rapor ve mobil görünüm", async ({
    page,
}) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.goto("/login");
    await page.locator("[name=email]").fill(credentials.email);
    await page.locator("[name=password]").fill(credentials.password);
    await page.getByRole("button", { name: "Giriş yap" }).click();
    await expect(page).toHaveURL(/\/$/);
    await expect(
        page.getByRole("heading", { name: "Genel bakış." }),
    ).toBeVisible();
    await page.screenshot({
        path: "storage/framework/dashboard-desktop.png",
        fullPage: true,
    });
    await page
        .getByRole("link", { name: "Yeni vardiya", exact: true })
        .first()
        .click();
    await page
        .locator("[name=employee_id]")
        .selectOption({ label: "Test Personeli · Petrol İstasyonu" });
    await page.locator("[name=sales]").fill("1000.10");
    await page.locator("[name=credit]").fill("100");
    await page.locator("[name=fast]").fill("50");
    await page.locator("[name=expense]").fill("25");
    await page.locator("[name=points]").fill("10");
    await page.locator("[name=pos_kuveyt]").fill("200");
    await page.locator("[name=pos_garanti]").fill("100");
    await page.locator("[name=pos_ziraat]").fill("50");
    await page.locator("[name=pos_other]").fill("5");
    await page.locator("[name=counted_cash]").fill("455.10");
    await page.locator("[name=coins]").fill("5");
    await expect(page.locator(".cash-result")).toContainText("Kasa dengeli");
    await expect(page.locator(".cash-result strong")).toContainText("0,00");
    await page.locator("[name=counted_cash]").fill("");
    await expect(page.locator(".cash-result")).toContainText("Sayım bekliyor");
    await page.locator("[name=counted_cash]").fill("0");
    await expect(page.locator(".cash-result")).toContainText("Eksik kasa");
    await page
        .locator("[name=department_id]")
        .selectOption({ label: "Market" });
    await expect(page.locator("[name=credit]")).toBeDisabled();
    await expect(page.locator(".calc-row").nth(1)).toContainText("570,10");
    await page
        .locator("[name=employee_id]")
        .selectOption({ label: "Test Personeli · Market" });
    await page.locator("[name=counted_cash]").fill("565.10");
    await page.locator("[name=status]").selectOption("pending");
    await page.getByRole("button", { name: "Vardiyayı kaydet" }).click();
    await expect(page.locator(".alert.success")).toContainText(
        "Vardiya kaydedildi",
    );
    await expect(page.locator(".cash-result")).toContainText("Kasa dengeli");
    page.on("dialog", (dialog) => dialog.accept());
    await page.getByRole("button", { name: "Vardiyayı onayla" }).click();
    await expect(page.locator(".page-heading")).toContainText("Onaylandı");
    await page.getByRole("button", { name: "Vardiyayı kapat" }).click();
    await expect(page.locator(".page-heading")).toContainText("Kapatıldı");
    await page.goto("/reports?report=monthly");
    await expect(
        page.getByRole("heading", { name: "Seçilen raporun özeti" }),
    ).toBeVisible();
    await expect(page.locator("canvas").first()).toBeVisible();
    const download = page.waitForEvent("download");
    await page.getByRole("link", { name: "PDF", exact: true }).click();
    expect((await download).suggestedFilename()).toBe("vardiya-raporu.pdf");
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/");
    await expect(
        page.getByRole("heading", { name: "Genel bakış." }),
    ).toBeVisible();
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
    await page.getByRole("button", { name: "Menü", exact: true }).click();
    await expect(
        page.getByRole("link", { name: "Personel", exact: true }),
    ).toBeVisible();
    await page.getByRole("link", { name: "Personel", exact: true }).click();
    await expect(
        page.getByRole("heading", { name: "Personel", exact: true }),
    ).toBeVisible();
    await page.screenshot({
        path: "storage/framework/mobile-personnel.png",
        fullPage: true,
    });
    expect(errors).toEqual([]);
});

test('ayrıntılı grafikler ve tema çalışır', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto('/login');
  await page.locator('[name=email]').fill(credentials.email);
  await page.locator('[name=password]').fill(credentials.password);
  await page.getByRole('button', { name: 'Giriş yap' }).click();
  await expect(page).toHaveURL(/\/$/);
  await page.getByText('Ayrıntılı analizleri göster', { exact: false }).click();
  await expect(page.locator('canvas')).toHaveCount(7);
  for (const canvas of await page.locator('canvas').all()) {
    await expect(canvas).toBeVisible();
  }
  await page.getByRole('button', { name: 'Temayı değiştir' }).click();
  await expect(page.locator('html')).toHaveClass(/dark/);
  await page.getByRole('button', { name: 'Temayı değiştir' }).click();
  await page.screenshot({ path: 'storage/framework/dashboard-populated-test.png', fullPage: true });
  expect(errors).toEqual([]);
});
