import { mkdir, readFile, writeFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";

const root = resolve(import.meta.dirname, "..");
const collections = {};

async function collection(name) {
  if (!collections[name]) {
    collections[name] = JSON.parse(await readFile(resolve(root, `node_modules/@iconify-json/${name}/icons.json`), "utf8"));
  }
  return collections[name];
}

const icons = {
  "google-drive": ["logos", "google-drive"],
  gmail: ["logos", "google-gmail"],
  "google-meet": ["logos", "google-meet"],
  "google-calendar": ["logos", "google-calendar"],
  "google-docs": ["thesvg-color", "google-docs"],
  "google-sheets": ["thesvg-color", "google-sheets"],
  "google-slides": ["thesvg-color", "google-slides"],
  "google-chat": ["thesvg-color", "google-chat"],
  "google-forms": ["thesvg-color", "google-forms"],
  "google-keep": ["logos", "google-keep"],
  "google-sites": ["thesvg-color", "google-sites-2026"],
  "google-tasks": ["thesvg-color", "google-tasks"],
  "microsoft-outlook": ["thesvg-color", "microsoft-outlook"],
  "microsoft-teams": ["thesvg-color", "microsoft-teams"],
  "microsoft-onedrive": ["thesvg-color", "microsoft-onedrive"],
  "microsoft-word": ["thesvg-color", "microsoft-word"],
  "microsoft-excel": ["thesvg-color", "microsoft-excel"],
  "microsoft-powerpoint": ["thesvg-color", "microsoft-powerpoint"],
  "microsoft-onenote": ["thesvg-color", "microsoft-onenote"],
  "microsoft-sharepoint": ["thesvg-color", "microsoft-sharepoint"],
  "microsoft-planner": ["thesvg-color", "microsoft-planner"],
  "microsoft-forms": ["selfhst", "microsoft-forms"],
  "microsoft-todo": ["thesvg-color", "microsoft-todo"],
  "microsoft-loop": ["thesvg-color", "microsoft-loop"],
};

const destination = resolve(root, "public/apps/integrations");
await mkdir(destination, { recursive: true });

for (const [filename, [collectionName, iconName]] of Object.entries(icons)) {
  const data = await collection(collectionName);
  const icon = data.icons[iconName];
  if (!icon) throw new Error(`Ícone ausente: ${collectionName}:${iconName}`);
  const width = icon.width || data.width || 24;
  const height = icon.height || data.height || 24;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}">${icon.body}</svg>\n`;
  await writeFile(resolve(destination, `${filename}.svg`), svg);
}

console.log(`${Object.keys(icons).length} ícones exportados para ${destination}`);
