import { Card, Space, Typography } from 'antd';

const { Paragraph, Title } = Typography;

export default function PageSectionHeader({
  title,
  description = '',
  extra = null,
  className = '',
}) {
  return (
    <Card className={`page-card page-section-header ${className}`.trim()} bordered={false}>
      <Space
        align="start"
        style={{ width: '100%', justifyContent: 'space-between' }}
        wrap
        className="page-section-header-inner"
      >
        <Space direction="vertical" size={6} style={{ minWidth: 0 }} className="page-section-header-main">
          <Title level={3} style={{ margin: 0 }}>
            {title}
          </Title>
          {description ? (
            <Paragraph className="page-description" style={{ margin: 0 }}>
              {description}
            </Paragraph>
          ) : null}
        </Space>
        {extra ? <div className="page-section-header-extra">{extra}</div> : null}
      </Space>
    </Card>
  );
}
