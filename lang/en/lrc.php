<?php

return [
  'title' => ':lrc Editor & Timing Tool',
  'audioplayer' => 'Audio Player',
  'timingeditor' => 'String Timing',

  'player_ctt' => ':a [:k]',
  'player_nofile' => 'No audio file selected',
  'player_selectfile' => 'Select audio file',
  'player_playpause' => 'Play/Pause',
  'player_stop' => 'Stop',
  'player_voldec' => 'Decrease volume',
  'player_volinc' => 'Increase volume',
  'player_seek' => 'Seek',
  'player_syncmode' => 'Syncing mode',
  'player_editmode' => 'Editing mode',
  'player_switch_syncmode' => 'Switch to sync mode',
  'player_switch_editmode' => 'Switch to edit mode',

  'timing_import' => 'Import .LRC file',
  'toggle_merge' => 'Merging of identical lines is',
  'merge_explainer' => 'Some programs might not support multiple timestamps in front of lyric lines which this editor exports by default, so this can be disabled. Click here to toggle. This setting is saved between page reloads.',
  'timing_export' => 'Export as .LRC',
  'timing_exportwithmeta' => '(with metadata)',
  'timing_exportnometa' => '(without metadata)',
  'timing_pastelyrics' => 'Import raw lyrics',
  'timing_wipe' => 'Discard lyrics',
  'timing_entry_empty' => '<break>',
  'timing_addrowup' => 'Add row above',
  'timing_addrowdown' => 'Add row below',
  'timing_addrowdown_ts' => 'Add row below with same timestamp',
  'timing_remrow' => 'Remove row',
  'timing_sync' => 'Set timestamp to current audio position & move down',
  'timing_sync_break' => 'Add break entry above with current timestamp & move down',
  'timing_sync_jump' => 'Move to next/previous entry',
  'timing_sync_remove' => 'Clear timestamp of current entry & move down',
  'timing_goto' => 'Jump to timestamp',
  'timing_metadata' => 'Metadata',

  'dialog_pasteraw_title' => 'Paste raw lyrics',
  'dialog_pasteraw_info' => 'Each line will be turned into a separate time-able entry. This will replace any entries currently present in the editor.',
  'dialog_pasteraw_action' => 'Import',
  'dialog_parse_error' => 'Parse error',
  'dialog_parse_error_empty' => 'The specified file is empty',
  'dialog_format_error' => 'Unsupported file format',
  'dialog_format_notaudio' => 'You need to select an audio file',
  'dialog_shortcut_info' => 'How-To & Keyboard shortcuts',
  'dialog_edit_meta' => 'Edit LRC metadata fields',
  'dialog_edit_meta_reset_info' => "If you've made any changes but want to use the metadata from the current audio file then use this.",
  'dialog_edit_meta_reset_btn' => 'Restore default values',

  'metadata_field_placeholders' => [
    'artist' => 'Artist',
    'album' => 'Album',
    'title' => 'Title',
    'lyrics_author' => 'Lyrics by',
    'length' => 'Audio length',
    'file_author' => 'File creator',
    'offset' => 'Global offset',
    'created_with' => 'Name of creating application',
    'version' => 'Version of creating application',
  ],

  'fileformat' => 'LRC file format',
  'fileformat_url' => 'https://en.wikipedia.org/wiki/LRC_(file_format)',
  'player' => 'player',
  'editor' => 'editor',
  'editor_scroll_lock' => "If your cursor is above the editor it won't automatically scroll the current entry into view.",
  'twomodes' => 'two modes',
  'tsformat' => 'MM:SS.ss',
  'howto' => [
    'opening' => [
      'This tool provides an easy and intuitive way to synchronize song lyrics to any audio file your browser can play. Below you will find the instructions on how you can re-sync existing LRC files as well as how you can time lyrics you found online to your favourite songs.',
      'Although not required for using this tool, you might want to familiarize yourself with the :lrcff which this editor is designed to work with. Knowing how the file is structured could help you get a better understanding of the UI.',
    ],
    'limitations' => [
      'title' => 'Limitations & known issues',
      "Due to weird handling of HTML5 :t tags that play audio files opened on the client-side there's a chance that the audio will randomly skip to the beginning and continue playing from there, while the player continues to display the time as if nothing changed. This is actually a browser bug which I've seen happen in Chrome 58 myself; basically the browser continues to report that the playback is working fine while in reality it's not, and there's no way for me to detect when this happens via code, so watch out for that. If this happens to you reload the page and/or restart the browser.",
      "The tool isn't capable of handling the insertion or moving of mid-lyric timestamps (denoted by e.g. :dur within an entry), mainly because the software I use to display my lyrics (:app) does not support it and displays the timestamps as if it was part of the lyrics. Adding support for this would also be difficult given the editing interface I've already made, so this is unlikely to be supported by this tool in the future.",
    ],
    'ui' => [
      'title' => 'User Interface',
      'The interface consists of two main parts: an audio player and the lyrics editing/timing area.',
      "The :player cannot be used until a file is selected by pressing the first button and browsing for an audio file. If you provide an unsupported format you will see an error message, otherwise the file should've loaded properly, and you should be able to see the track length, name and format below the seek bar. From this point, you can use the buttons to the left or the shortcuts (shown at the bottom of this dialog) to control playback. You can also interact with the seek bar directly; clicking on or dragging along the line will move the audio position there. Once you start editing the lyrics you may notice that little white lines will appear on the seek bar; those lines represent an entry in the editor and server the purpose of giving you an overview of where each string is on the timeline. The thumb is actually slightly transparent so you can see when it passes over an entry. They don't have any added functionality besides looking cool.",
      "The :editor is the main part of the application. At the top you will see a series of buttons. The first is a mode selector which will switch between sync and edit mode, more on that later. The second and third buttons allow you to import/export LRC files, respectively. These are drop-down buttons that present additional options when pressed. For example, an option in the Import drop-down opens a dialog that you can paste song lyrics into and have the inserted into the editor ready for syncing. The fourth button lets you edit the metadata that will be added to the LRC file when exported using the appropriate option. The last button is for clearing all entries in the editor to start fresh, which is essentially the same as reloading the page, but you won't have to re-select the audio file again this way.",
      "As previously mentioned the editor has :tm, the first of which is :em which you see by default. Here you can edit both the timestamp and the text by simply typing in new values. The buttons on the right should be self-explanatory: the first one adds a row above the current, the next does the same but below, and then there's a button for removing the row. If you play the song while in this mode, rows with timestamps will be highlighted in green indicating that they would be displayed at the current time in the song by an application utilizing this data.",
      "The (second) :sm is where the magic happens. To switch to this mode you need to have an audio file selected, because the idea is that you play the song while pressing keys to alter the timestamps in each row. In sync mode you can use additional hotkeys to do just that. At the bottom of this dialog there's a list of all shortcuts that can be used in this mode with explanations on how they work, so you should check that for more information. The button at the end of each row becomes active when there's a valid timestamp in said row, and when clicked it will move the player precisely to that position. When you switch to this mode, a \"handle\" is set on the first entry in the editor, indicated by the blue text &amp; background. You can hold down :alt while clicking the :sm button to set the handle on the current (green) entry instead. This handle is what dictates where the timestamp will go (or not go) once you press the appropriate keys. When the handle reaches the last entry in the editor and you press :enter the editor will switch back to edit mode, because it's assumed that you've finished syncing the lyrics and also because there's no next entry to go to. Moving around with the arrow key will not cause an automatic mode switch.",
      "When you use the raw lyrics import dialog you might get some extra new lines (break entries) in the editor. An entry is considered a break entry when there's a timestamp but the text is left blank. These appearing after an import is caused by empty lines being present in the lyrics. Most software will not display any lyric until the next one or display the next faded out for example.",
      "Be sure to use the correct time format when changing timestamps in edit mode. As far as I've seen :tsf (minutes, seconds, 100ths of a second) is safe to use, so if you use anything else the editor will consider it invalid (indicated by the red color &amp; background on the input). The important thing to note is that you can't use hours, only minutes, seconds and milliseconds. The milliseconds part is optional, but at the very least a timestamp indicating \"at 1 second\" has to have a minute component, like this: :ex. Rows that contain an invalid timestamp will not be included in exported LRC files.",
    ],
    'shortcuts' => [
      'title' => 'Keyboard Shortcuts',
      'player' => [
        'title' => 'Player controls (Always usable)',
        'volstep' => 'Steps of :in by default, holding :shift changes step to 10%, :alt to 1%.',
        'seekstep' => 'Steps of 2.5 seconds by default, holding :shift changes step to 5 seconds.',
      ],
      'editor' => 'Editor controls (Only in sync mode)',
    ],
  ],
];