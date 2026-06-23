import { useEffect, useState } from 'react';
import { Button, Form, Image, Input, Space, Typography } from 'antd';
import MediaPickerModal from '@/components/MediaPickerModal';
import { getResourceDetail } from '@/api/resources';
import { resolveAssetUrl } from '@/utils/media';

const { Text } = Typography;

export default function CoverAssetField({
  form,
  fieldName = 'cover_asset_id',
  label = '封面图片',
  helper = '从资源中心选择一张图片作为列表缩略图或详情主图。',
}) {
  const assetId = Form.useWatch(fieldName, form);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [asset, setAsset] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const id = Number(assetId || 0);
    if (!id) {
      setAsset(null);
      return;
    }

    let disposed = false;
    setLoading(true);

    getResourceDetail(id)
      .then((record) => {
        if (!disposed) {
          setAsset(record || null);
        }
      })
      .catch(() => {
        if (!disposed) {
          setAsset(null);
        }
      })
      .finally(() => {
        if (!disposed) {
          setLoading(false);
        }
      });

    return () => {
      disposed = true;
    };
  }, [assetId]);

  return (
    <>
      <Form.Item name={fieldName} hidden>
        <Input />
      </Form.Item>

      <Form.Item label={label}>
        <div className="media-field-shell media-field-shell-wide">
          <div className="media-field-preview media-field-preview-wide">
            {asset?.file_path ? (
              <Image
                src={resolveAssetUrl(asset.thumbnail_url || asset.file_path)}
                alt={asset.file_name || '封面预览'}
                width={200}
                height={132}
                style={{ objectFit: 'cover', borderRadius: 12 }}
                preview
              />
            ) : (
              <div className="media-field-empty media-field-empty-wide">
                {loading ? '加载中...' : '暂无预览'}
              </div>
            )}
          </div>

          <div className="media-field-meta">
            <Space wrap>
              <Button type="primary" onClick={() => setPickerOpen(true)}>
                {assetId ? '更换图片' : '选择图片'}
              </Button>
              {assetId ? (
                <Button
                  onClick={() => {
                    form.setFieldValue(fieldName, 0);
                    setAsset(null);
                  }}
                >
                  移除
                </Button>
              ) : null}
            </Space>

            <Text type="secondary">{`已选资源 ID：${assetId || '未选择'}`}</Text>

            {asset?.file_name ? (
              <Space direction="vertical" size={2}>
                <Text strong>{asset.file_name}</Text>
                <Text type="secondary">{asset.file_path}</Text>
              </Space>
            ) : (
              <Text type="secondary">{helper}</Text>
            )}
          </div>
        </div>
      </Form.Item>

      <MediaPickerModal
        open={pickerOpen}
        title="选择封面图片"
        assetType="image"
        selectedAssetId={Number(assetId || 0)}
        onCancel={() => setPickerOpen(false)}
        onSelect={(selectedAsset) => {
          form.setFieldValue(fieldName, Number(selectedAsset.id || 0));
          setAsset(selectedAsset);
          setPickerOpen(false);
        }}
      />
    </>
  );
}
