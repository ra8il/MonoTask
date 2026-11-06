<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Notes | TODO LIST</title>
    <link rel="stylesheet" href="todo_style.css">
    <link rel="stylesheet" href="quick_notes_style.css">
</head>
<body class="qn-page">

<!-- icon sprite -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-save" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></symbol>
    <symbol id="i-undo" viewBox="0 0 24 24"><path d="M1 4v6h6"/><path d="M3.5 15a9 9 0 1 0 2.1-9.4L1 10"/></symbol>
    <symbol id="i-redo" viewBox="0 0 24 24"><path d="M23 4v6h-6"/><path d="M20.5 15a9 9 0 1 1-2.1-9.4L23 10"/></symbol>
    <symbol id="i-ul" viewBox="0 0 24 24"><path d="M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01"/></symbol>
    <symbol id="i-ol" viewBox="0 0 24 24"><path d="M10 6h11M10 12h11M10 18h11"/><path d="M4 5l1.5-1v5.5"/><path d="M3.5 14a1.3 1.3 0 0 1 2.5.4c0 1-2.5 1.8-2.5 3H6"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><rect x="3" y="4" width="6" height="6" rx="1"/><path d="M4.5 7l1.2 1.2L8 5.8"/><rect x="3" y="14" width="6" height="6" rx="1"/><path d="M13 7h8M13 17h8"/></symbol>
    <symbol id="i-image" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></symbol>
    <symbol id="i-link" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></symbol>
    <symbol id="i-table" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="1"/><path d="M3 10h18M3 15h18M9 4v16"/></symbol>
    <symbol id="i-rowplus" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="12" rx="1"/><path d="M3 9h18M12 3v12M12 18v4M10 20h4"/></symbol>
    <symbol id="i-colplus" viewBox="0 0 24 24"><rect x="3" y="3" width="12" height="18" rx="1"/><path d="M3 9h12M3 15h12M9 3v18M18 12h4M20 10v4"/></symbol>
    <symbol id="i-tabledel" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="1"/><path d="M8 9l8 6M16 9l-8 6"/></symbol>
    <symbol id="i-hr" viewBox="0 0 24 24"><path d="M3 12h18"/></symbol>
    <symbol id="i-quote" viewBox="0 0 24 24"><path d="M4 8h5v5a4 4 0 0 1-4 4"/><path d="M14 8h5v5a4 4 0 0 1-4 4"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></symbol>
    <symbol id="i-left" viewBox="0 0 24 24"><path d="M3 6h18M3 12h12M3 18h16"/></symbol>
    <symbol id="i-center" viewBox="0 0 24 24"><path d="M3 6h18M6 12h12M4 18h16"/></symbol>
    <symbol id="i-right" viewBox="0 0 24 24"><path d="M3 6h18M9 12h12M5 18h16"/></symbol>
    <symbol id="i-clear" viewBox="0 0 24 24"><path d="M4 7V4h16v3M9 20h4M12 4v16M16 16l5 5M21 16l-5 5"/></symbol>
    <symbol id="i-trash" viewBox="0 0 24 24"><path d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></symbol>
    <symbol id="i-copy" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></symbol>
    <symbol id="i-print" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></symbol>
</svg>

<?php include 'includes/nav.php'; ?>

<main id="qn-root">

    <!-- ================= header: file bar + ribbon ================= -->
    <header class="qn-head">
        <div class="qn-filebar">
            <button id="qn-side-toggle" class="qn-btn qn-icon" type="button" title="Show or hide the notes list" aria-label="Notes list">
                <svg class="qn-ic"><use href="#i-menu"/></svg>
            </button>
            <button id="qn-new" class="qn-btn qn-icon qn-primary" type="button" title="New note" aria-label="New note">
                <svg class="qn-ic"><use href="#i-plus"/></svg>
            </button>
            <button id="qn-file-btn" class="qn-btn" type="button" data-pop="qn-pop-file">
                File <svg class="qn-ic qn-ic-sm"><use href="#i-chevron"/></svg>
            </button>

            <span class="qn-vsep"></span>

            <div class="qn-tabs" role="tablist">
                <button class="qn-tab active" type="button" role="tab" data-tab="home">Home</button>
                <button class="qn-tab" type="button" role="tab" data-tab="insert">Insert</button>
            </div>

            <input id="qn-title" class="qn-title" type="text" maxlength="150" placeholder="Untitled note" autocomplete="off" aria-label="Note title">
            <span id="qn-status" class="qn-status">Saved</span>
            <button id="qn-save" class="qn-btn" type="button" title="Save (Ctrl+S)">
                <svg class="qn-ic"><use href="#i-save"/></svg> Save
            </button>
        </div>

        <div id="qn-ribbon" class="qn-ribbon">
            <!-- ---- Home ---- -->
            <div class="qn-panel" data-panel="home">
                <div class="qn-group">
                    <button class="qn-tb" type="button" data-act="undo" title="Undo (Ctrl+Z)"><svg class="qn-ic"><use href="#i-undo"/></svg></button>
                    <button class="qn-tb" type="button" data-act="redo" title="Redo (Ctrl+Y)"><svg class="qn-ic"><use href="#i-redo"/></svg></button>
                </div>
                <div class="qn-group">
                    <select id="qn-style" class="qn-sel" title="Paragraph style">
                        <option value="p">Normal</option>
                        <option value="h1">Heading 1</option>
                        <option value="h2">Heading 2</option>
                        <option value="h3">Heading 3</option>
                        <option value="blockquote">Quote</option>
                    </select>
                    <select id="qn-font" class="qn-sel" title="Font">
                        <option value="Arial">Arial</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Times New Roman">Times New Roman</option>
                        <option value="Verdana">Verdana</option>
                        <option value="Trebuchet MS">Trebuchet MS</option>
                        <option value="Courier New">Courier New</option>
                        <option value="Comic Sans MS">Comic Sans</option>
                    </select>
                    <select id="qn-size" class="qn-sel qn-sel-sm" title="Font size">
                        <option>10</option><option>12</option><option>14</option><option selected>16</option>
                        <option>18</option><option>20</option><option>24</option><option>28</option>
                        <option>32</option><option>40</option><option>48</option>
                    </select>
                </div>
                <div class="qn-group">
                    <button class="qn-tb qn-letter" type="button" data-act="bold" title="Bold (Ctrl+B)"><b>B</b></button>
                    <button class="qn-tb qn-letter" type="button" data-act="italic" title="Italic (Ctrl+I)"><i>I</i></button>
                    <button class="qn-tb qn-letter" type="button" data-act="underline" title="Underline (Ctrl+U)"><u>U</u></button>
                    <button class="qn-tb qn-letter" type="button" data-act="strikeThrough" title="Strikethrough"><s>S</s></button>
                </div>
                <div class="qn-group">
                    <button id="qn-fc-btn" class="qn-tb qn-color" type="button" data-pop="qn-pop-color" title="Text color">
                        <span class="qn-color-letter">A</span><span id="qn-fc-bar" class="qn-color-bar"></span>
                    </button>
                    <button id="qn-hl-btn" class="qn-tb qn-color" type="button" data-pop="qn-pop-hl" title="Highlight color">
                        <span class="qn-color-letter qn-hl-letter">A</span><span id="qn-hl-bar" class="qn-color-bar"></span>
                    </button>
                </div>
                <div class="qn-group">
                    <button class="qn-tb" type="button" data-act="justifyLeft" title="Align left"><svg class="qn-ic"><use href="#i-left"/></svg></button>
                    <button class="qn-tb" type="button" data-act="justifyCenter" title="Center"><svg class="qn-ic"><use href="#i-center"/></svg></button>
                    <button class="qn-tb" type="button" data-act="justifyRight" title="Align right"><svg class="qn-ic"><use href="#i-right"/></svg></button>
                </div>
                <div class="qn-group">
                    <button class="qn-tb" type="button" data-act="insertUnorderedList" title="Bullet list"><svg class="qn-ic"><use href="#i-ul"/></svg></button>
                    <button class="qn-tb" type="button" data-act="insertOrderedList" title="Numbered list"><svg class="qn-ic"><use href="#i-ol"/></svg></button>
                    <button class="qn-tb" type="button" data-act="checklist" title="Checklist"><svg class="qn-ic"><use href="#i-check"/></svg></button>
                </div>
                <div class="qn-group">
                    <button class="qn-tb" type="button" data-act="clear" title="Clear formatting"><svg class="qn-ic"><use href="#i-clear"/></svg></button>
                </div>
            </div>

            <!-- ---- Insert ---- -->
            <div class="qn-panel" data-panel="insert" hidden>
                <div class="qn-group">
                    <button class="qn-tb qn-wide" type="button" data-act="image" title="Insert image (or paste / drop one)"><svg class="qn-ic"><use href="#i-image"/></svg><span>Image</span></button>
                    <button class="qn-tb qn-wide" type="button" data-pop="qn-pop-link" title="Insert link"><svg class="qn-ic"><use href="#i-link"/></svg><span>Link</span></button>
                </div>
                <div class="qn-group">
                    <button class="qn-tb qn-wide" type="button" data-pop="qn-pop-table" title="Insert table"><svg class="qn-ic"><use href="#i-table"/></svg><span>Table</span></button>
                    <button class="qn-tb" type="button" data-act="table-row" title="Add row below" disabled><svg class="qn-ic"><use href="#i-rowplus"/></svg></button>
                    <button class="qn-tb" type="button" data-act="table-col" title="Add column to the right" disabled><svg class="qn-ic"><use href="#i-colplus"/></svg></button>
                    <button class="qn-tb" type="button" data-act="table-del" title="Delete table" disabled><svg class="qn-ic"><use href="#i-tabledel"/></svg></button>
                </div>
                <div class="qn-group">
                    <button class="qn-tb qn-wide" type="button" data-act="checklist" title="Checklist"><svg class="qn-ic"><use href="#i-check"/></svg><span>Checklist</span></button>
                    <button class="qn-tb qn-wide" type="button" data-act="quote" title="Quote"><svg class="qn-ic"><use href="#i-quote"/></svg><span>Quote</span></button>
                    <button class="qn-tb qn-wide" type="button" data-act="hr" title="Divider line"><svg class="qn-ic"><use href="#i-hr"/></svg><span>Divider</span></button>
                    <button class="qn-tb qn-wide" type="button" data-act="datetime" title="Insert date and time"><svg class="qn-ic"><use href="#i-clock"/></svg><span>Date &amp; time</span></button>
                </div>
            </div>
        </div>
    </header>

    <!-- ================= sidebar + editor ================= -->
    <div class="qn-main">
        <aside id="qn-side" class="qn-side">
            <div class="qn-side-search">
                <input id="qn-search" type="search" placeholder="Search notes" autocomplete="off" aria-label="Search notes">
            </div>
            <div id="qn-list" class="qn-list"></div>
        </aside>

        <div id="qn-scroll" class="qn-scroll">
            <div id="qn-editor" class="qn-editor" contenteditable="true" spellcheck="true" role="textbox" aria-multiline="true" aria-label="Note"></div>

            <div id="qn-imgui" class="qn-imgui" hidden>
                <div class="qn-imgbar">
                    <button type="button" data-w="25">25%</button>
                    <button type="button" data-w="50">50%</button>
                    <button type="button" data-w="75">75%</button>
                    <button type="button" data-w="100">100%</button>
                    <button type="button" data-w="del" class="qn-imgdel" title="Remove image" aria-label="Remove image"><svg class="qn-ic qn-ic-sm"><use href="#i-trash"/></svg></button>
                </div>
                <div id="qn-imghandle" class="qn-imghandle" title="Drag to resize"></div>
            </div>
        </div>
    </div>

    <!-- ================= footer quality-of-life tools ================= -->
    <footer class="qn-foot">
        <div class="qn-foot-tools">
            <button class="qn-ft" type="button" data-act="heading-cycle" title="Heading: tap to cycle H1, H2, H3, plain text"><b>H</b></button>
            <button class="qn-ft" type="button" data-act="insertUnorderedList" title="Bullet list"><svg class="qn-ic"><use href="#i-ul"/></svg></button>
            <button class="qn-ft" type="button" data-act="insertOrderedList" title="Numbered list"><svg class="qn-ic"><use href="#i-ol"/></svg></button>
            <button class="qn-ft" type="button" data-act="checklist" title="Checklist"><svg class="qn-ic"><use href="#i-check"/></svg></button>
            <button class="qn-ft" type="button" data-act="image" title="Insert image"><svg class="qn-ic"><use href="#i-image"/></svg></button>
            <span class="qn-vsep"></span>
            <button id="qn-goto-btn" class="qn-ft" type="button" data-pop="qn-pop-goto" data-dir="up" title="Go to heading (Ctrl+/)"><b>/</b></button>
        </div>
        <div class="qn-foot-stats">
            <span id="qn-words">0 words</span>
            <span id="qn-chars">0 characters</span>
        </div>
    </footer>

    <!-- ================= popovers ================= -->
    <div id="qn-pop-file" class="qn-pop qn-menu" hidden>
        <button type="button" data-file="new"><svg class="qn-ic"><use href="#i-plus"/></svg>New note</button>
        <button type="button" data-file="save"><svg class="qn-ic"><use href="#i-save"/></svg>Save now<kbd>Ctrl+S</kbd></button>
        <button type="button" data-file="duplicate"><svg class="qn-ic"><use href="#i-copy"/></svg>Duplicate</button>
        <hr>
        <button type="button" data-file="html"><svg class="qn-ic"><use href="#i-download"/></svg>Download as .html</button>
        <button type="button" data-file="txt"><svg class="qn-ic"><use href="#i-file"/></svg>Download as .txt</button>
        <button type="button" data-file="print"><svg class="qn-ic"><use href="#i-print"/></svg>Print / save as PDF</button>
        <hr>
        <button type="button" data-file="delete" class="qn-danger"><svg class="qn-ic"><use href="#i-trash"/></svg>Delete note</button>
    </div>

    <div id="qn-pop-color" class="qn-pop" hidden>
        <div class="qn-swatches" id="qn-fc-swatches"></div>
        <label class="qn-custom">Custom <input id="qn-fc-custom" type="color" value="#ff6b6b"></label>
    </div>

    <div id="qn-pop-hl" class="qn-pop" hidden>
        <div class="qn-swatches" id="qn-hl-swatches"></div>
        <label class="qn-custom">Custom <input id="qn-hl-custom" type="color" value="#ffd93d"></label>
    </div>

    <div id="qn-pop-link" class="qn-pop qn-form" hidden>
        <input id="qn-link-url" type="text" placeholder="Paste or type a link" autocomplete="off">
        <div class="qn-form-row">
            <button id="qn-link-remove" type="button" class="qn-btn">Remove link</button>
            <button id="qn-link-apply" type="button" class="qn-btn qn-primary">Apply</button>
        </div>
    </div>

    <div id="qn-pop-table" class="qn-pop qn-form" hidden>
        <div class="qn-form-row">
            <label>Rows <input id="qn-rows" type="number" min="1" max="20" value="3"></label>
            <label>Columns <input id="qn-cols" type="number" min="1" max="10" value="3"></label>
        </div>
        <div class="qn-form-row">
            <button id="qn-table-apply" type="button" class="qn-btn qn-primary">Insert table</button>
        </div>
    </div>

    <div id="qn-pop-goto" class="qn-pop qn-goto" hidden>
        <input id="qn-goto-input" type="text" placeholder="Go to heading…" autocomplete="off">
        <div id="qn-goto-list" class="qn-goto-list"></div>
    </div>

    <input id="qn-file" type="file" accept="image/*" multiple hidden>

    <!-- confirm dialog -->
    <div id="qn-modal" class="qn-modal" aria-hidden="true">
        <div class="qn-panel-box" role="alertdialog" aria-labelledby="qn-confirm-title" aria-describedby="qn-confirm-msg">
            <h3 id="qn-confirm-title"></h3>
            <p id="qn-confirm-msg"></p>
            <div class="qn-panel-actions">
                <button id="qn-confirm-cancel" type="button" class="qn-btn">Cancel</button>
                <button id="qn-confirm-ok" type="button" class="qn-btn qn-danger-btn">Delete</button>
            </div>
        </div>
    </div>

    <div id="qn-toast" class="qn-toast" role="status" aria-live="polite"></div>
</main>

<script src="quick_notes.js"></script>
</body>
</html>
