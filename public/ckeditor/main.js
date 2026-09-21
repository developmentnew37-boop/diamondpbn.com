/**
 * This configuration was generated using the CKEditor 5 Builder. You can modify it anytime using this link:
 * https://ckeditor.com/ckeditor-5/builder/?redirect=portal#installation/NoNgNARATAdCMEYKQCwGY0AYAcUCs2KAnHgglCCGiJhTjVFIZnipurishAKYB2yTGGAIwQoaIkBdSEWwBjeQCMU8iFKA
 */
// import ClassicEditor from 'ckeditor5/build/classic';

const {
	ClassicEditor,
	Autoformat,
	AutoImage,
	Autosave,
	Base64UploadAdapter,
	BlockQuote,
	Bold,
	CloudServices,
	Emoji,
	Essentials,
	Heading,
	ImageBlock,
	ImageCaption,
	ImageInline,
	ImageInsert,
	ImageInsertViaUrl,
	ImageResize,
	ImageStyle,
	ImageTextAlternative,
	ImageToolbar,
	ImageUpload,
	Indent,
	IndentBlock,
	Italic,
	Link,
	LinkImage,
	List,
	ListProperties,
	MediaEmbed,
	Mention,
	Paragraph,
	PasteFromOffice,
	Table,
	TableCaption,
	TableCellProperties,
	TableColumnResize,
	TableProperties,
	TableToolbar,
	TextTransformation,
	TodoList,
	Underline,
	GeneralHtmlSupport
} = window.CKEDITOR;

const LICENSE_KEY =
	'eyJhbGciOiJFUzI1NiJ9.eyJleHAiOjE3OTEyNDQ3OTksImp0aSI6IjZlMGQzNjg2LTY5NDQtNGVmMC05N2YyLTdmOTMzNjJkZThkNiIsInVzYWdlRW5kcG9pbnQiOiJodHRwczovL3Byb3h5LWV2ZW50LmNrZWRpdG9yLmNvbSIsImRpc3RyaWJ1dGlvbkNoYW5uZWwiOlsiY2xvdWQiLCJkcnVwYWwiLCJzaCJdLCJ3aGl0ZUxhYmVsIjp0cnVlLCJsaWNlbnNlVHlwZSI6InRyaWFsIiwiZmVhdHVyZXMiOlsiKiJdLCJyZW1vdmVGZWF0dXJlcyI6WyJBSSJdLCJ2YyI6IjkxZjkyODc0In0.8KaC4bgypqGUhncOMdh9mGVQI6SGcXUuB2fOLmMXYz9DxjcUvK4IZKeQ6q1CnWLpB7Nc7HDvO5DKelVrF5SdUA';

const editorConfig = {
	toolbar: {
		items: [
			'undo',
			'redo',
			'|',
			'heading',
			'|',
			'bold',
			'italic',
			'underline',
			'|',
			'emoji',
			'link',
			'insertImage',
			'mediaEmbed',
			'insertTable',
			'blockQuote',
			'|',
			'bulletedList',
			'numberedList',
			'todoList',
			'outdent',
			'indent'
		],
		shouldNotGroupWhenFull: false
	},
	plugins: [
		Autoformat,
		AutoImage,
		Autosave,
		Base64UploadAdapter,
		BlockQuote,
		Bold,
		CloudServices,
		Emoji,
		Essentials,
		Heading,
		ImageBlock,
		ImageCaption,
		ImageInline,
		ImageInsert,
		ImageInsertViaUrl,
		ImageResize,
		ImageStyle,
		ImageTextAlternative,
		ImageToolbar,
		ImageUpload,
		Indent,
		IndentBlock,
		Italic,
		Link,
		LinkImage,
		List,
		ListProperties,
		MediaEmbed,
		Mention,
		Paragraph,
		PasteFromOffice,
		Table,
		TableCaption,
		TableCellProperties,
		TableColumnResize,
		TableProperties,
		TableToolbar,
		TextTransformation,
		TodoList,
		Underline
	],
	heading: {
		options: [
			{
				model: 'paragraph',
				title: 'Paragraph',
				class: 'ck-heading_paragraph'
			},
			{
				model: 'heading1',
				view: 'h1',
				title: 'Heading 1',
				class: 'ck-heading_heading1'
			},
			{
				model: 'heading2',
				view: 'h2',
				title: 'Heading 2',
				class: 'ck-heading_heading2'
			},
			{
				model: 'heading3',
				view: 'h3',
				title: 'Heading 3',
				class: 'ck-heading_heading3'
			},
			{
				model: 'heading4',
				view: 'h4',
				title: 'Heading 4',
				class: 'ck-heading_heading4'
			},
			{
				model: 'heading5',
				view: 'h5',
				title: 'Heading 5',
				class: 'ck-heading_heading5'
			},
			{
				model: 'heading6',
				view: 'h6',
				title: 'Heading 6',
				class: 'ck-heading_heading6'
			}
		]
	},
	image: {
		toolbar: [
			'toggleImageCaption',
			'imageTextAlternative',
			'|',
			'imageStyle:inline',
			'imageStyle:wrapText',
			'imageStyle:breakText',
			'|',
			'resizeImage'
		]
	},
	initialData: "",
	licenseKey: LICENSE_KEY,
	link: {
		addTargetToExternalLinks: true,
		defaultProtocol: 'https://',
		decorators: {
			toggleDownloadable: {
				mode: 'manual',
				label: 'Downloadable',
				attributes: {
					download: 'file'
				}
			}
		}
	},
	list: {
		properties: {
			styles: true,
			startIndex: true,
			reversed: true
		}
	},
	mention: {
		feeds: [
			{
				marker: '@',
				feed: [
					/* See: https://ckeditor.com/docs/ckeditor5/latest/features/mentions.html */
				]
			}
		]
	},
	placeholder: 'Type or paste your content here!',
	table: {
		contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties']
	}, htmlSupport: {
		allow: [
			{
				name: /.*/,
				attributes: true,
				classes: true,
				styles: true
			}
		]
	}
};

function articleHasMeaningfulContent(html) {
	const tmp = document.createElement('div');
	tmp.innerHTML = html || '';
	const text = (tmp.textContent || '')
		.replace(/\u00a0/g, ' ')
		.replace(/\s+/g, ' ')
		.trim();

	return text.length > 0;
}

function bindRequiredArticleContent(editor, textarea) {
	if (!editor || !textarea) {
		return;
	}

	const form = textarea.closest('form');
	if (!form) {
		return;
	}

	let errorEl = document.getElementById('article-content-error');
	if (!errorEl) {
		errorEl = document.createElement('p');
		errorEl.id = 'article-content-error';
		errorEl.className = 'hidden text-red-400 bg-red-100 text-sm !p-2 rounded';
		textarea.insertAdjacentElement('beforebegin', errorEl);
	}

	form.addEventListener('submit', (event) => {
		const html = editor.getData();
		textarea.value = html;

		if (articleHasMeaningfulContent(html)) {
			errorEl.classList.add('hidden');
			errorEl.textContent = '';
			return;
		}

		event.preventDefault();
		errorEl.textContent = 'Article content is required.';
		errorEl.classList.remove('hidden');
		errorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
		editor.editing.view.focus();
	});
}

if (document.querySelector('#editor')) {
	ClassicEditor
		.create(document.querySelector('#editor'), editorConfig)
		.then((editor) => {
			bindRequiredArticleContent(editor, document.querySelector('#editor'));
		})
		.catch((error) => {
			console.error(error);
		});
}

if (document.querySelector('#update-editor')) {
	const el = document.querySelector('#update-editor');
	const initialContent = el.value || '';

	ClassicEditor
		.create(el, editorConfig)
		.then((editor) => {
			editor.setData(initialContent);
			bindRequiredArticleContent(editor, el);
		})
		.catch((error) => {
			console.error(error);
		});
}

