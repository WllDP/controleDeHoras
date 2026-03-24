const fs = require("fs");
const path = require("path");

const pkgPath = path.resolve(__dirname, "..", "package.json");
const outPath = path.resolve(__dirname, "..", "www", "app-version.json");

const pkgRaw = fs.readFileSync(pkgPath, "utf-8");
const pkg = JSON.parse(pkgRaw);
const version = String(pkg.version || "").trim();

if (!version) {
  throw new Error("Version not found in package.json");
}

const payload = {
  version,
  generatedAt: new Date().toISOString(),
};

fs.writeFileSync(outPath, JSON.stringify(payload, null, 2));
console.log(`[version] wrote ${outPath} (${version})`);
