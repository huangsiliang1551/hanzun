const fs = require('fs');
const path = require('path');

const backendRoot = path.resolve(__dirname, '..');
const translationServicePath = path.join(backendRoot, 'app', 'service', 'translation', 'TranslationService.php');
const navigationServicePath = path.join(backendRoot, 'app', 'service', 'content', 'NavigationService.php');

function assert(condition, message, issues) {
  if (!condition) {
    issues.push(message);
  }
}

function main() {
  const issues = [];
  const translationService = fs.readFileSync(translationServicePath, 'utf8');
  const navigationService = fs.readFileSync(navigationServicePath, 'utf8');

  const forbidden = [
    '娴溠冩惂',
    '鏂规',
    '鏂囩珷',
    '璇佷功',
    '鑱旂郴鏂瑰紡',
    '鍙傛暟鏍￠獙澶辫触',
    '璁板綍涓嶅瓨鍦'
  ];

  forbidden.forEach((fragment) => {
    assert(!translationService.includes(fragment), `TranslationService 仍包含乱码片段: ${fragment}`, issues);
    assert(!navigationService.includes(fragment), `NavigationService 仍包含乱码片段: ${fragment}`, issues);
  });

  const expectedTranslationLabels = [
    "'product' => '产品'",
    "'solution' => '方案'",
    "'article' => '文章'",
    "'page' => '单页/专题页'",
    "'team_member' => '团队成员'",
    "'certificate' => '证书'",
    "'navigation_menu' => '导航菜单'",
    "'navigation_item' => '导航项'",
    "'contact_field_type' => '联系方式类型'",
    "'contact_item' => '联系方式'",
    "'about_page' => '企业介绍页面'",
    "'about_block' => '企业介绍模块'",
    "'homepage_section' => '首页配置模块'"
  ];

  expectedTranslationLabels.forEach((label) => {
    assert(translationService.includes(label), `TranslationService 缺少预期实体标签: ${label}`, issues);
  });

  const expectedNavigationMessages = [
    "throw new BusinessException('记录不存在', ErrorCode::NOT_FOUND);",
    "throw new BusinessException('参数校验失败', ErrorCode::INVALID_PARAMS);"
  ];

  expectedNavigationMessages.forEach((message) => {
    assert(navigationService.includes(message), `NavigationService 缺少预期提示文案: ${message}`, issues);
  });

  if (issues.length > 0) {
    console.error('Readable service copy validation failed:');
    issues.forEach((issue) => console.error(`- ${issue}`));
    process.exit(1);
  }

  console.log('Readable service copy validation passed.');
}

main();
