import { Button, Card, Space, Tag, Typography } from 'antd';

const { Paragraph, Text, Title } = Typography;

export default function PagePlaceholder({
  title,
  description,
  eyebrow = '',
  tags = [],
  primaryAction,
  secondaryAction,
  hideHeader = false,
  compact = false,
  children,
}) {
  return (
    <Card className={`page-card${compact ? ' page-card-compact' : ''}`}>
      <Space direction="vertical" size={16} style={{ width: '100%' }} className="page-placeholder-shell">
        {!hideHeader ? (
          <div className="page-placeholder-header">
            <Space direction="vertical" size={6} style={{ minWidth: 0 }} className="page-placeholder-head-main">
              {eyebrow ? <Text className="section-eyebrow">{eyebrow}</Text> : null}
              <Title level={3} style={{ margin: 0 }}>
                {title}
              </Title>
              {description ? (
                <Paragraph className="placeholder-meta" style={{ margin: 0 }}>
                  {description}
                </Paragraph>
              ) : null}
              {tags.length > 0 ? (
                <Space size={[8, 8]} wrap className="page-placeholder-tags">
                  {tags.map((tag) => (
                    <Tag key={tag} color="blue">
                      {tag}
                    </Tag>
                  ))}
                </Space>
              ) : null}
            </Space>

            {primaryAction || secondaryAction ? (
              <div className="placeholder-actions">
                {secondaryAction ? (
                  <Button onClick={secondaryAction.onClick}>{secondaryAction.label}</Button>
                ) : null}
                {primaryAction ? (
                  <Button
                    type="primary"
                    loading={primaryAction.loading}
                    onClick={primaryAction.onClick}
                  >
                    {primaryAction.label}
                  </Button>
                ) : null}
              </div>
            ) : null}
          </div>
        ) : null}

        {children}
      </Space>
    </Card>
  );
}
