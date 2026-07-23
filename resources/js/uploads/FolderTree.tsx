import { FunctionComponent } from 'preact';
import { useMemo } from 'preact/compat';

export interface UploadFolderData {
  id: string;
  parent_id: string | null;
  name: string;
  disable_thumbnails: boolean;
  disable_conversion: boolean;
  upload_count: number;
  upload_key: string;
}

interface FolderTreeProps {
  folders: UploadFolderData[];
  selectedId: string | null;
  onSelect: (id: string | null) => void;
}

interface FolderNodeProps {
  folder: UploadFolderData;
  depth: number;
  childrenByParent: Map<string | null, UploadFolderData[]>;
  selectedId: string | null;
  onSelect: (id: string | null) => void;
}

const FolderNode: FunctionComponent<FolderNodeProps> = ({
  folder, depth, childrenByParent, selectedId, onSelect,
}) => {
  const children = childrenByParent.get(folder.id) || [];

  return (
    <li>
      <a
        href={`?folder=${folder.id}`}
        className={`list-group-item list-group-item-action${folder.id === selectedId ? ' active' : ''}`}
        style={{ paddingLeft: `${1 + depth}rem` }}
        onClick={e => {
          e.preventDefault();
          onSelect(folder.id);
        }}
      >
        <span className="fa fa-folder me-1" />
        {folder.name}
        <span className="badge bg-secondary float-end">{folder.upload_count}</span>
      </a>
      {children.length > 0 && (
        <ul className="list-unstyled mb-0">
          {children.map(child => (
            <FolderNode
              key={child.id}
              folder={child}
              depth={depth + 1}
              childrenByParent={childrenByParent}
              selectedId={selectedId}
              onSelect={onSelect}
            />
          ))}
        </ul>
      )}
    </li>
  );
};

export const FolderTree: FunctionComponent<FolderTreeProps> = ({ folders, selectedId, onSelect }) => {
  const childrenByParent = useMemo(() => {
    const map = new Map<string | null, UploadFolderData[]>();
    folders.forEach(folder => {
      const key = folder.parent_id;
      if (!map.has(key)) map.set(key, []);
      (map.get(key) as UploadFolderData[]).push(folder);
    });
    return map;
  }, [folders]);

  const roots = childrenByParent.get(null) || [];

  return (
    <>
      <ul className="list-group list-unstyled mb-0" id="upload-folder-tree-list">
        <li>
          <a
            href="?"
            className={`list-group-item list-group-item-action${selectedId === null ? ' active' : ''}`}
            onClick={e => {
              e.preventDefault();
              onSelect(null);
            }}
          >
            <span className="fa fa-home me-1" />
            {window.Laravel.jsLocales['folder-root']}
          </a>
        </li>
        {roots.map(folder => (
          <FolderNode
            key={folder.id}
            folder={folder}
            depth={0}
            childrenByParent={childrenByParent}
            selectedId={selectedId}
            onSelect={onSelect}
          />
        ))}
      </ul>
      {folders.length === 0 && (
        <p className="text-muted small mt-2 mb-0">{window.Laravel.jsLocales['folder-empty']}</p>
      )}
    </>
  );
};
