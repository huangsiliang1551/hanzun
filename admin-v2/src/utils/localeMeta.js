import { resolveAssetUrl } from '@/utils/media';

const ISO_LANGUAGE_PATTERN = /^[a-z]{2,3}(-[a-z0-9]{2,8})*$/i;
const ISO_COUNTRY_PATTERN = /^[A-Z]{2}$/;

export const LANGUAGE_PRESETS = [
  { code: 'zh', zhName: '普通话', englishName: 'Mandarin Chinese', nativeName: '中文' },
  { code: 'en', zhName: '英语', englishName: 'English', nativeName: 'English' },
  { code: 'es', zhName: '西班牙语', englishName: 'Spanish', nativeName: 'Español' },
  { code: 'hi', zhName: '印地语', englishName: 'Hindi', nativeName: 'हिन्दी' },
  { code: 'ar', zhName: '阿拉伯语', englishName: 'Arabic', nativeName: 'العربية' },
  { code: 'fr', zhName: '法语', englishName: 'French', nativeName: 'Français' },
  { code: 'de', zhName: '德语', englishName: 'German', nativeName: 'Deutsch' },
  { code: 'ja', zhName: '日语', englishName: 'Japanese', nativeName: '日本語' },
  { code: 'pt', zhName: '葡萄牙语', englishName: 'Portuguese', nativeName: 'Português' },
  { code: 'ru', zhName: '俄语', englishName: 'Russian', nativeName: 'Русский' },
  { code: 'it', zhName: '意大利语', englishName: 'Italian', nativeName: 'Italiano' },
  { code: 'ko', zhName: '韩语', englishName: 'Korean', nativeName: '한국어' },
  { code: 'tr', zhName: '土耳其语', englishName: 'Turkish', nativeName: 'Türkçe' },
  { code: 'nl', zhName: '荷兰语', englishName: 'Dutch', nativeName: 'Nederlands' },
  { code: 'pl', zhName: '波兰语', englishName: 'Polish', nativeName: 'Polski' },
  { code: 'vi', zhName: '越南语', englishName: 'Vietnamese', nativeName: 'Tiếng Việt' },
  { code: 'th', zhName: '泰语', englishName: 'Thai', nativeName: 'ไทย' },
  { code: 'sv', zhName: '瑞典语', englishName: 'Swedish', nativeName: 'Svenska' },
  { code: 'id', zhName: '印尼语', englishName: 'Indonesian', nativeName: 'Bahasa Indonesia' },
  { code: 'el', zhName: '希腊语', englishName: 'Greek', nativeName: 'Ελληνικά' },
  { code: 'cs', zhName: '捷克语', englishName: 'Czech', nativeName: 'Čeština' },
  { code: 'hu', zhName: '匈牙利语', englishName: 'Hungarian', nativeName: 'Magyar' },
  { code: 'ro', zhName: '罗马尼亚语', englishName: 'Romanian', nativeName: 'Română' },
  { code: 'uk', zhName: '乌克兰语', englishName: 'Ukrainian', nativeName: 'Українська' },
  { code: 'ms', zhName: '马来语', englishName: 'Malay', nativeName: 'Bahasa Melayu' },
  { code: 'he', zhName: '希伯来语', englishName: 'Hebrew', nativeName: 'עברית' },
  { code: 'fa', zhName: '波斯语', englishName: 'Persian', nativeName: 'فارسی' },
  { code: 'ur', zhName: '乌尔都语', englishName: 'Urdu', nativeName: 'اردو' },
  { code: 'bn', zhName: '孟加拉语', englishName: 'Bengali', nativeName: 'বাংলা' },
  { code: 'fil', zhName: '菲律宾语', englishName: 'Filipino', nativeName: 'Filipino' },
  { code: 'my', zhName: '缅甸语', englishName: 'Burmese', nativeName: 'မြန်မာ' },
  { code: 'km', zhName: '高棉语', englishName: 'Khmer', nativeName: 'ខ្មែរ' },
  { code: 'lo', zhName: '老挝语', englishName: 'Lao', nativeName: 'ລາວ' },
  { code: 'si', zhName: '僧伽罗语', englishName: 'Sinhala', nativeName: 'සිංහල' },
  { code: 'sw', zhName: '斯瓦希里语', englishName: 'Swahili', nativeName: 'Kiswahili' },
  { code: 'no', zhName: '挪威语', englishName: 'Norwegian', nativeName: 'Norsk' },
  { code: 'da', zhName: '丹麦语', englishName: 'Danish', nativeName: 'Dansk' },
  { code: 'fi', zhName: '芬兰语', englishName: 'Finnish', nativeName: 'Suomi' },
  { code: 'bg', zhName: '保加利亚语', englishName: 'Bulgarian', nativeName: 'Български' },
  { code: 'sr', zhName: '塞尔维亚语', englishName: 'Serbian', nativeName: 'Српски' },
  { code: 'hr', zhName: '克罗地亚语', englishName: 'Croatian', nativeName: 'Hrvatski' },
  { code: 'sl', zhName: '斯洛文尼亚语', englishName: 'Slovenian', nativeName: 'Slovenščina' },
  { code: 'lt', zhName: '立陶宛语', englishName: 'Lithuanian', nativeName: 'Lietuvių' },
  { code: 'lv', zhName: '拉脱维亚语', englishName: 'Latvian', nativeName: 'Latviešu' },
  { code: 'et', zhName: '爱沙尼亚语', englishName: 'Estonian', nativeName: 'Eesti' },
];

const COUNTRY_CODES_FALLBACK = [
  'AE', 'AR', 'AT', 'AU', 'BD', 'BE', 'BG', 'BH', 'BR', 'BY', 'CA', 'CH', 'CL', 'CN', 'CO', 'CR',
  'CZ', 'DE', 'DK', 'DO', 'DZ', 'EC', 'EE', 'EG', 'ES', 'FI', 'FR', 'GB', 'GE', 'GH', 'GR',
  'GT', 'HK', 'HR', 'HU', 'ID', 'IE', 'IL', 'IN', 'IQ', 'IR', 'IS', 'IT', 'JM', 'JO', 'JP',
  'KE', 'KH', 'KR', 'KW', 'KZ', 'LA', 'LB', 'LK', 'LT', 'LU', 'LV', 'MA', 'MD', 'ME', 'MK',
  'MM', 'MN', 'MO', 'MX', 'MY', 'NG', 'NL', 'NO', 'NP', 'NZ', 'OM', 'PA', 'PE', 'PH', 'PK',
  'PL', 'PR', 'PT', 'PY', 'QA', 'RO', 'RS', 'RU', 'SA', 'SE', 'SG', 'SI', 'SK', 'SV', 'TH',
  'TN', 'TR', 'TW', 'UA', 'US', 'UY', 'VE', 'VN', 'ZA',
];

function detectSupportedCountries() {
  if (typeof Intl.supportedValuesOf === 'function') {
    try {
      return Intl.supportedValuesOf('region');
    } catch {
      // Fallback to static list when Intl data is unavailable.
    }
  }
  return COUNTRY_CODES_FALLBACK;
}

const COUNTRY_CODES = Array.from(new Set((detectSupportedCountries() || []).filter((code) => ISO_COUNTRY_PATTERN.test(code))));
const COUNTRY_CODE_SET = new Set(COUNTRY_CODES);

const COUNTRY_DIAL_CODE_MAP = {
  '1': 'US',
  '7': 'RU',
  '20': 'EG',
  '27': 'ZA',
  '30': 'GR',
  '31': 'NL',
  '32': 'BE',
  '33': 'FR',
  '34': 'ES',
  '36': 'HU',
  '39': 'IT',
  '40': 'RO',
  '41': 'CH',
  '43': 'AT',
  '44': 'GB',
  '45': 'DK',
  '46': 'SE',
  '47': 'NO',
  '48': 'PL',
  '49': 'DE',
  '51': 'PE',
  '52': 'MX',
  '54': 'AR',
  '55': 'BR',
  '56': 'CL',
  '57': 'CO',
  '58': 'VE',
  '60': 'MY',
  '61': 'AU',
  '62': 'ID',
  '63': 'PH',
  '64': 'NZ',
  '65': 'SG',
  '66': 'TH',
  '81': 'JP',
  '82': 'KR',
  '84': 'VN',
  '86': 'CN',
  '90': 'TR',
  '91': 'IN',
  '92': 'PK',
  '93': 'AF',
  '94': 'LK',
  '95': 'MM',
  '98': 'IR',
  '212': 'MA',
  '213': 'DZ',
  '216': 'TN',
  '218': 'LY',
  '220': 'GM',
  '221': 'SN',
  '223': 'ML',
  '225': 'CI',
  '230': 'MU',
  '233': 'GH',
  '234': 'NG',
  '248': 'SC',
  '249': 'SD',
  '251': 'ET',
  '254': 'KE',
  '255': 'TZ',
  '256': 'UG',
  '260': 'ZM',
  '263': 'ZW',
  '351': 'PT',
  '352': 'LU',
  '353': 'IE',
  '354': 'IS',
  '355': 'AL',
  '356': 'MT',
  '357': 'CY',
  '358': 'FI',
  '359': 'BG',
  '370': 'LT',
  '371': 'LV',
  '372': 'EE',
  '373': 'MD',
  '374': 'AM',
  '375': 'BY',
  '380': 'UA',
  '381': 'RS',
  '385': 'HR',
  '386': 'SI',
  '420': 'CZ',
  '421': 'SK',
  '852': 'HK',
  '853': 'MO',
  '855': 'KH',
  '856': 'LA',
  '880': 'BD',
  '886': 'TW',
  '960': 'MV',
  '971': 'AE',
  '972': 'IL',
  '974': 'QA',
  '975': 'BT',
  '976': 'MN',
  '977': 'NP',
  '998': 'UZ',
};

const LANGUAGE_COUNTRY_HINTS = {
  ar: ['AE', 'SA', 'EG'],
  de: ['DE', 'AT', 'CH'],
  en: ['US', 'GB', 'AU', 'CA'],
  es: ['ES', 'MX', 'AR'],
  fr: ['FR', 'BE', 'CA'],
  hi: ['IN'],
  id: ['ID'],
  it: ['IT'],
  ja: ['JP'],
  ko: ['KR'],
  ms: ['MY'],
  nl: ['NL'],
  pl: ['PL'],
  pt: ['PT', 'BR'],
  ro: ['RO'],
  ru: ['RU'],
  el: ['GR'],
  cs: ['CZ'],
  hu: ['HU'],
  sv: ['SE'],
  th: ['TH'],
  tr: ['TR'],
  uk: ['UA'],
  ur: ['PK'],
  vi: ['VN'],
  zh: ['CN', 'HK', 'TW', 'SG'],
};

function getLanguageCountryFallback(languageCode) {
  const normalized = normalizeLanguageCode(languageCode);
  if (!normalized) {
    return '';
  }

  const [baseCode, regionCode] = normalized.split('-');
  const hinted = LANGUAGE_COUNTRY_HINTS[baseCode];
  const firstHint = String((Array.isArray(hinted) ? hinted[0] : '') || '').toUpperCase();
  if (ISO_COUNTRY_PATTERN.test(firstHint)) {
    return firstHint;
  }

  if (ISO_COUNTRY_PATTERN.test(String(regionCode || '').toUpperCase())) {
    return String(regionCode || '').toUpperCase();
  }

  if (ISO_COUNTRY_PATTERN.test(baseCode.toUpperCase())) {
    return baseCode.toUpperCase();
  }

  try {
    const maximizedLocale = new Intl.Locale(baseCode).maximize();
    if (ISO_COUNTRY_PATTERN.test(String(maximizedLocale.region || '').toUpperCase())) {
      return String(maximizedLocale.region).toUpperCase();
    }
  } catch {
    // Ignore unsupported locales.
  }

  return '';
}

function getDisplayNames(locale, type) {
  try {
    return new Intl.DisplayNames([locale], { type });
  } catch {
    return null;
  }
}

function safeDisplayName(displayNames, code, fallback = '') {
  if (!displayNames || !code) {
    return fallback;
  }

  try {
    return String(displayNames.of(code) || fallback).trim();
  } catch {
    return fallback;
  }
}

function normalizeLanguageCode(code) {
  const raw = String(code || '').trim();
  if (!raw) {
    return '';
  }

  const compact = raw.replace(/_/g, '-').toLowerCase();
  if (!ISO_LANGUAGE_PATTERN.test(compact)) {
    return '';
  }

  const [base, ...rest] = compact.split('-');
  return [base, ...rest.map((part) => (part.length === 2 ? part.toUpperCase() : part))].join('-');
}

function normalizeCountryCode(code) {
  const raw = String(code || '').trim();
  if (!raw) {
    return '';
  }

  const normalized = raw.toUpperCase();
  if (ISO_COUNTRY_PATTERN.test(normalized)) {
    return normalized;
  }

  const digits = raw.replace(/[^\d]/g, '');
  if (digits && COUNTRY_DIAL_CODE_MAP[digits]) {
    return COUNTRY_DIAL_CODE_MAP[digits];
  }

  return '';
}

function toFlagEmoji(code) {
  const normalized = normalizeCountryCode(code);
  if (!ISO_COUNTRY_PATTERN.test(normalized)) {
    return '';
  }

  return Array.from(normalized)
    .map((char) => String.fromCodePoint(127397 + char.charCodeAt(0)))
    .join('');
}

function toFlagImageUrl(code) {
  const normalized = normalizeCountryCode(code);
  if (!ISO_COUNTRY_PATTERN.test(normalized)) {
    return '';
  }

  const lowerCode = normalized.toLowerCase();
  return resolveAssetUrl(`/assets/images/flags/${lowerCode}.svg`);
}

function findLanguagePreset(code) {
  const normalized = normalizeLanguageCode(code);
  return LANGUAGE_PRESETS.find((item) => item.code === normalized) || null;
}

export function findLanguageName(code) {
  return getLanguageMeta(code).zhName || '';
}

export function getNativeLanguageName(code, fallback = '') {
  const normalized = normalizeLanguageCode(code);
  if (!normalized) {
    return fallback;
  }

  try {
    const nativeName = new Intl.DisplayNames([normalized], { type: 'language' }).of(normalized);
    if (nativeName && String(nativeName).trim()) {
      return String(nativeName).trim();
    }
  } catch {
    // Ignore unsupported locales.
  }

  return fallback;
}

function buildLanguageLabel(meta) {
  const parts = [];

  if (meta.nativeName) {
    parts.push(meta.nativeName);
  }
  if (
    meta.englishName &&
    meta.englishName !== meta.nativeName &&
    meta.englishName !== meta.zhName
  ) {
    parts.push(meta.englishName);
  }

  return `${parts.join(' / ')} (${meta.code.toUpperCase()})`;
}

export function getLanguageMeta(code, enabledLanguages = []) {
  const normalized = normalizeLanguageCode(code);
  if (!normalized) {
    const fallback = String(code || '').trim();
    return {
      code: fallback,
      name: fallback,
      zhName: fallback,
      englishName: fallback,
      nativeName: fallback,
      label: fallback,
    };
  }

  const existing = (Array.isArray(enabledLanguages) ? enabledLanguages : []).find(
    (item) => normalizeLanguageCode(item?.code) === normalized,
  );
  const preset = findLanguagePreset(normalized);
  const fallbackLabel = normalized.toUpperCase();

  const zhDisplayNames = getDisplayNames('zh-Hans', 'language');
  const enDisplayNames = getDisplayNames('en', 'language');
  const computedZhName = safeDisplayName(zhDisplayNames, normalized, '');
  const computedEnglishName = safeDisplayName(enDisplayNames, normalized, '');
  const computedNativeName = getNativeLanguageName(normalized, '');

  const zhName = String(
    existing?.zh_name || preset?.zhName || computedZhName || fallbackLabel,
  ).trim();
  const englishName = String(
    existing?.english_name || preset?.englishName || computedEnglishName || fallbackLabel,
  ).trim();
  const nativeName = String(
    existing?.native_name || preset?.nativeName || computedNativeName || englishName || zhName,
  ).trim();
  const name = nativeName || zhName || englishName || fallbackLabel;

  return {
    code: normalized,
    name,
    zhName,
    englishName,
    nativeName,
    label: buildLanguageLabel({
      code: normalized,
      zhName,
      englishName,
      nativeName,
    }),
  };
}

export function getLanguageMetaWithFlag(code) {
  const normalized = normalizeLanguageCode(code);
  if (!normalized) {
    return {
      ...getLanguageMeta(code),
      flag: '',
      flagUrl: '',
      flagCountryCode: '',
      flagCountryName: '',
    };
  }

  const countryCode = getLanguageCountryFallback(normalized);

  if (!ISO_COUNTRY_PATTERN.test(countryCode)) {
    return {
      ...getLanguageMeta(normalized),
      flag: '',
      flagUrl: '',
      flagCountryCode: '',
      flagCountryName: '',
    };
  }

  const countryMeta = getCountryMeta(countryCode);

  return {
    ...getLanguageMeta(normalized),
    flag: countryMeta.flag,
    flagUrl: countryMeta.flagUrl,
    flagCountryCode: countryMeta.code,
    flagCountryName: countryMeta.name,
  };
}

export function getCountryMeta(code) {
  const normalized = normalizeCountryCode(code);
  if (!normalized) {
    const fallback = String(code || '').trim();
    return {
      code: fallback,
      name: fallback,
      englishName: fallback,
      flag: '',
      flagUrl: '',
      label: fallback,
    };
  }

  const zhDisplayNames = getDisplayNames('zh-Hans', 'region');
  const enDisplayNames = getDisplayNames('en', 'region');
  const name = safeDisplayName(zhDisplayNames, normalized, normalized);
  const englishName = safeDisplayName(enDisplayNames, normalized, normalized);
  const flag = toFlagEmoji(normalized);
  const flagUrl = toFlagImageUrl(normalized);

  return {
    code: normalized,
    name,
    englishName,
    flag,
    flagUrl,
    label: `${flag ? `${flag} ` : ''}${name} (${normalized})`,
  };
}

function buildLanguageSearchText(meta) {
  return [meta.code, meta.name, meta.zhName, meta.englishName, meta.nativeName, meta.label]
    .join(' ')
    .toLowerCase();
}

function buildCountrySearchText(meta) {
  return [meta.code, meta.name, meta.englishName, meta.label].join(' ').toLowerCase();
}

export function buildLanguageOptions(enabledLanguages = []) {
  const enabled = Array.isArray(enabledLanguages) ? enabledLanguages : [];
  const seen = new Set();
  const prioritized = [];
  const fallback = [];

  enabled.forEach((item) => {
    const meta = getLanguageMeta(item?.code, enabled);
    const normalizedCode = normalizeLanguageCode(meta.code);
    if (!normalizedCode || seen.has(normalizedCode)) {
      return;
    }

    seen.add(normalizedCode);
    prioritized.push({
      value: normalizedCode,
      label: meta.label,
      searchText: buildLanguageSearchText(meta),
    });
  });

  LANGUAGE_PRESETS.forEach((item) => {
    const meta = getLanguageMeta(item.code, enabled);
    const normalizedCode = normalizeLanguageCode(meta.code);
    if (!normalizedCode || seen.has(normalizedCode)) {
      return;
    }

    seen.add(normalizedCode);
    fallback.push({
      value: normalizedCode,
      label: meta.label,
      searchText: buildLanguageSearchText(meta),
    });
  });

  return [...prioritized, ...fallback];
}

export function buildCountryOptions(enabledLanguages = []) {
  const enabled = Array.isArray(enabledLanguages) ? enabledLanguages : [];
  const recommendedCodes = [];
  const seenCodes = new Set();

  enabled.forEach((item) => {
    const languageCode = normalizeLanguageCode(item?.code);
    const countryCode = getLanguageCountryFallback(languageCode);
    if (!countryCode || seenCodes.has(countryCode)) {
      return;
    }

    seenCodes.add(countryCode);
    recommendedCodes.push(countryCode);
  });

  const allCodes = [...recommendedCodes, ...COUNTRY_CODES.filter((code) => !seenCodes.has(code))];
  return allCodes.map((code) => {
    const meta = getCountryMeta(code);
    return {
      value: meta.code,
      label: meta.label,
      searchText: buildCountrySearchText(meta),
    };
  });
}

export function filterLocaleOption(input, option) {
  const keyword = String(input || '').trim().toLowerCase();
  if (!keyword) {
    return true;
  }

  return String(option?.searchText || option?.label || option?.value || '')
    .toLowerCase()
    .includes(keyword);
}

export function formatGeoSummary(countryCode, languageCode, enabledLanguages = []) {
  return {
    country: getCountryMeta(countryCode),
    language: getLanguageMeta(languageCode, enabledLanguages),
  };
}
