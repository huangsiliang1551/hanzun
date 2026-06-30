const fs = require("fs");
const path = require("path");

const backendRoot = path.resolve(__dirname, "..");
const repositoryPath = path.join(backendRoot, "app", "repository", "PublicChatRepository.php");
const schemaPath = path.join(backendRoot, "database", "sql", "001_init_schema.sql");

function read(filePath) {
  return fs.existsSync(filePath) ? fs.readFileSync(filePath, "utf8") : "";
}

function assert(pattern, content, message, issues) {
  if (!pattern.test(content)) {
    issues.push(message);
  }
}

function main() {
  const repository = read(repositoryPath);
  const schema = read(schemaPath);
  const issues = [];

  assert(/CREATE TABLE IF NOT EXISTS `visitor_events`/m, schema, "schema missing visitor_events table", issues);
  assert(/`session_code`\s+VARCHAR\(64\)\s+NOT NULL/m, schema, "visitor_events must persist session_code", issues);
  assert(/`page`\s+VARCHAR\(255\)\s+NOT NULL/m, schema, "visitor_events must persist page path", issues);
  assert(/`language_code`\s+VARCHAR\(16\)\s+NOT NULL/m, schema, "visitor_events must persist language code", issues);
  assert(/INSERT INTO visitor_events/m, repository, "PublicChatRepository must insert visitor events into database mode", issues);
  assert(/SELECT page,\s*title,\s*referrer,\s*visited_at,\s*language_code\s+FROM visitor_events/m, repository, "PublicChatRepository must read visitor events from database mode", issues);
  assert(/WHERE session_code = :session_code/m, repository, "visitor event queries must scope by session_code", issues);

  if (issues.length > 0) {
    console.error("Public chat DB mode validation failed:");
    issues.forEach((issue) => console.error(`- ${issue}`));
    process.exit(1);
  }

  console.log("Public chat DB mode validation passed.");
}

main();
