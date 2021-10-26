import { AudioPlayer } from './AudioPlayer';
import { TimingEditor } from './TimingEditor';

export interface PluginScope {
  player: AudioPlayer,
  editor: TimingEditor,
}
