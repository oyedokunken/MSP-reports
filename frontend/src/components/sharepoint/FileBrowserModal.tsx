import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Folder, File, ChevronRight, Home, Plus, CornerUpLeft } from 'lucide-react';
import { api } from '@/lib/api';
import { cn } from '@/lib/utils';

interface FileItem {
  id: string;
  name: string;
  type: 'folder' | 'file';
  web_url: string;
}

interface FileBrowserModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  integrationId: number | null;
  onSelect: (path: string) => void;
  initialPath?: string;
}

export function FileBrowserModal({
  open,
  onOpenChange,
  integrationId,
  onSelect,
  initialPath = ''
}: FileBrowserModalProps) {
  const [currentPath, setCurrentPath] = useState(initialPath);
  const [items, setItems] = useState<FileItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [newFolderName, setNewFolderName] = useState('');
  const [isCreatingFolder, setIsCreatingFolder] = useState(false);

  useEffect(() => {
    if (open && integrationId) {
      loadItems(currentPath);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, integrationId, currentPath]);

  const loadItems = async (path: string) => {
    setLoading(true);
    setError(null);
    try {
      const response = await api.get<{ data: FileItem[] }>(`/integrations/${integrationId}/sharepoint/browse`, {
        params: { path }
      });
      if (Array.isArray(response.data)) {
        setItems(response.data);
      } else {
        setItems(response.data || []);
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      setError(error.response?.data?.message || 'Failed to load folders');
    } finally {
      setLoading(false);
    }
  };

  const handleCreateFolder = async () => {
    if (!newFolderName.trim() || !integrationId) return;

    try {
      setLoading(true);
      await api.post(`/integrations/${integrationId}/sharepoint/folders`, {
        path: currentPath,
        name: newFolderName
      });
      setNewFolderName('');
      setIsCreatingFolder(false);
      loadItems(currentPath);
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      setError(error.response?.data?.message || 'Failed to create folder');
      setLoading(false);
    }
  };

  const handleNavigate = (folderName: string) => {
    const newPath = currentPath ? `${currentPath}/${folderName}` : folderName;
    setCurrentPath(newPath);
  };

  const handleNavigateUp = () => {
    if (!currentPath) return;
    const parts = currentPath.split('/');
    parts.pop();
    setCurrentPath(parts.join('/'));
  };

  const breadcrumbs = currentPath.split('/').filter(Boolean);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[700px] flex flex-col h-[80vh]">
        <DialogHeader className="flex-shrink-0">
          <DialogTitle>Select Destination Folder</DialogTitle>
          <DialogDescription>
            Choose a SharePoint folder to save reports to.
          </DialogDescription>
        </DialogHeader>

        <div className="flex items-center gap-2 p-2 bg-muted/50 rounded-md border min-h-[44px]">
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8 text-muted-foreground hover:text-foreground"
            onClick={() => setCurrentPath('')}
            disabled={!currentPath}
          >
            <Home className="h-4 w-4" />
          </Button>

          {currentPath && (
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8 text-muted-foreground hover:text-foreground mr-1"
              onClick={handleNavigateUp}
            >
              <CornerUpLeft className="h-4 w-4" />
            </Button>
          )}

          <div className="flex-1 flex items-center overflow-x-auto text-sm scrollbar-hide">
            {breadcrumbs.length > 0 ? (
              breadcrumbs.map((part, index) => (
                <div key={`${part}-${index}`} className="flex items-center whitespace-nowrap">
                  <ChevronRight className="h-4 w-4 text-muted-foreground mx-1 flex-shrink-0" />
                  <span className={cn(
                    "cursor-pointer hover:underline px-1",
                    index === breadcrumbs.length - 1 && "font-semibold text-foreground"
                  )}
                    onClick={() => {
                      const newPath = breadcrumbs.slice(0, index + 1).join('/');
                      setCurrentPath(newPath);
                    }}
                  >
                    {part}
                  </span>
                </div>
              ))
            ) : (
              <span className="text-muted-foreground ml-2">Root</span>
            )}
          </div>

          <Button
            variant="outline"
            size="sm"
            className="flex-shrink-0 gap-1 h-8"
            onClick={() => setIsCreatingFolder(!isCreatingFolder)}
          >
            <Plus className="h-3.5 w-3.5" />
            New Folder
          </Button>
        </div>

        {isCreatingFolder && (
          <div className="flex items-center gap-2 p-2 bg-muted/30 rounded border animate-in slide-in-from-top-2">
            <Folder className="h-4 w-4 text-primary" />
            <Input
              value={newFolderName}
              onChange={(e) => setNewFolderName(e.target.value)}
              placeholder="New Folder Name"
              className="h-8 text-sm"
              autoFocus
              onKeyDown={(e) => e.key === 'Enter' && handleCreateFolder()}
            />
            <Button size="sm" onClick={handleCreateFolder} disabled={loading || !newFolderName}>
              Create
            </Button>
          </div>
        )}

        <div className="flex-1 overflow-y-auto border rounded-md mt-2 relative bg-background">
          {loading ? (
            <div className="absolute inset-0 flex items-center justify-center bg-background/50 z-10">
              <Spinner />
            </div>
          ) : null}

          {error && (
            <div className="p-4 text-center text-sm text-destructive bg-destructive/5 m-2 rounded">
              {error}
            </div>
          )}

          {!loading && !error && items.length === 0 && (
            <div className="flex flex-col items-center justify-center h-full text-muted-foreground text-sm">
              <Folder className="h-10 w-10 mb-2 opacity-20" />
              <p>This folder is empty</p>
            </div>
          )}

          <div className="p-1 space-y-0.5">
            {items.map((item) => (
              <div
                key={item.id}
                className={cn(
                  "flex items-center gap-3 p-2 rounded-md cursor-pointer transition-colors text-sm hover:bg-muted/60",
                  item.type === 'folder' ? "text-foreground" : "text-muted-foreground"
                )}
                onClick={() => item.type === 'folder' ? handleNavigate(item.name) : null}
              >
                {item.type === 'folder' ? (
                  <Folder className="h-5 w-5 text-blue-500 fill-blue-500/20" />
                ) : (
                  <File className="h-5 w-5 text-gray-400" />
                )}
                <span className="flex-1 truncate font-medium">{item.name}</span>
                {item.type === 'file' && <span className="text-xs text-muted-foreground opacity-50">File</span>}
              </div>
            ))}
          </div>
        </div>

        <DialogFooter className="flex-shrink-0 mt-4">
          <div className="mr-auto text-xs text-muted-foreground self-center">
            Selected: <span className="font-mono text-foreground">{currentPath || '/'}</span>
          </div>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
          <Button onClick={() => {
            onSelect(currentPath);
            onOpenChange(false);
          }}>
            Select This Folder
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
