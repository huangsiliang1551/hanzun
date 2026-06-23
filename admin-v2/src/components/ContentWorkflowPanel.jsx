import { Alert, Button, Card, Descriptions, List, Popconfirm, Space, Tag, Typography } from 'antd';

const { Text } = Typography;

function renderEventLabel(action) {
  const labelMap = {
    draft_update: '草稿更新',
    published: '发布上线',
    restore_live: '恢复线上',
  };

  return labelMap[action] || action || 'event';
}

export default function ContentWorkflowPanel({
  workflow,
  detail,
  loading = false,
  restoring = false,
  onRestore,
}) {
  if (!detail && !workflow) {
    return null;
  }

  const hasChanges = Number(workflow?.has_unpublished_changes || 0) === 1;
  const hasLiveSnapshot = Number(workflow?.has_live_snapshot || 0) === 1;
  const publishLog = Array.isArray(workflow?.publish_log) ? workflow.publish_log : [];

  return (
    <Card
      size="small"
      loading={loading}
      title="工作流状态"
      extra={
        hasLiveSnapshot ? (
          <Popconfirm
            title="确认用当前线上快照恢复草稿吗？"
            okText="确认恢复"
            cancelText="取消"
            onConfirm={onRestore}
          >
            <Button size="small" loading={restoring}>
              恢复线上
            </Button>
          </Popconfirm>
        ) : null
      }
    >
      <Space direction="vertical" size={12} style={{ width: '100%' }}>
        <Alert
          type={hasChanges ? 'warning' : 'success'}
          showIcon
          message={hasChanges ? '当前草稿存在未发布改动。' : '当前草稿已与线上版本一致。'}
          description={
            workflow?.live_updated_at
              ? `线上更新时间：${workflow.live_updated_at}`
              : '当前内容还没有可恢复的线上快照。'
          }
        />

        <Space size={8} wrap>
          {hasChanges ? <Tag color="gold">待发布改动</Tag> : <Tag color="success">已与线上同步</Tag>}
          {hasLiveSnapshot ? <Tag color="blue">可恢复线上版本</Tag> : <Tag>暂无线上快照</Tag>}
        </Space>

        <Descriptions size="small" column={2} bordered>
          <Descriptions.Item label="草稿更新时间">
            {workflow?.draft_updated_at || detail?.updated_at || '-'}
          </Descriptions.Item>
          <Descriptions.Item label="线上更新时间">{workflow?.live_updated_at || '-'}</Descriptions.Item>
          <Descriptions.Item label="最后发布人">{workflow?.last_published_by || '-'}</Descriptions.Item>
          <Descriptions.Item label="最后恢复人">{workflow?.last_restored_by || '-'}</Descriptions.Item>
        </Descriptions>

        <List
          size="small"
          header="最近流程记录"
          dataSource={publishLog}
          locale={{ emptyText: '暂无流程记录。' }}
          renderItem={(item) => (
            <List.Item>
              <Space direction="vertical" size={2} style={{ width: '100%' }}>
                <Space size={8} wrap>
                  <Tag>{renderEventLabel(item.action)}</Tag>
                  <Text strong>{item.operator || 'system'}</Text>
                  <Text type="secondary">{item.created_at || '-'}</Text>
                </Space>
                <Text type="secondary">{item.message || '-'}</Text>
              </Space>
            </List.Item>
          )}
        />
      </Space>
    </Card>
  );
}
