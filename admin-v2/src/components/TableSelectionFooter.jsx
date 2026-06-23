import { Checkbox, Typography } from 'antd';

const { Text } = Typography;

function normalizeKey(value) {
  return String(value);
}

export default function TableSelectionFooter({
  rowKeys = [],
  selectedRowKeys = [],
  onChange,
  label = '\u5168\u9009',
  actions = null,
  pagination = null,
  extra = null,
}) {
  const safeRowKeys = Array.isArray(rowKeys)
    ? rowKeys.filter((item) => item !== null && item !== undefined)
    : [];

  if (safeRowKeys.length === 0) {
    return null;
  }

  const safeSelectedKeys = Array.isArray(selectedRowKeys) ? selectedRowKeys : [];
  const selectedMap = new Map(safeSelectedKeys.map((item) => [normalizeKey(item), item]));
  const selectedCountOnPage = safeRowKeys.filter((item) => selectedMap.has(normalizeKey(item))).length;
  const checked = selectedCountOnPage === safeRowKeys.length;
  const indeterminate = selectedCountOnPage > 0 && selectedCountOnPage < safeRowKeys.length;

  return (
    <div className="table-selection-footer">
      <div className="table-selection-footer-main">
        <div className="table-selection-footer-selection">
          <Checkbox
            checked={checked}
            indeterminate={indeterminate}
            onChange={(event) => {
              if (!onChange) {
                return;
              }

              if (event.target.checked) {
                const merged = new Map(selectedMap);
                safeRowKeys.forEach((item) => merged.set(normalizeKey(item), item));
                onChange(Array.from(merged.values()));
                return;
              }

              const pageKeySet = new Set(safeRowKeys.map((item) => normalizeKey(item)));
              onChange(safeSelectedKeys.filter((item) => !pageKeySet.has(normalizeKey(item))));
            }}
          >
            {label}
          </Checkbox>
          <Text type="secondary">{`\u5df2\u9009\u62e9 ${safeSelectedKeys.length} \u9879`}</Text>
        </div>
        {actions || extra ? (
          <div className="table-selection-footer-extra">
            {actions}
            {extra}
          </div>
        ) : null}
      </div>
      {pagination ? <div className="table-selection-footer-pagination">{pagination}</div> : null}
    </div>
  );
}
