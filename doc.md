# Bedrock Converse — native file type support

Reference for **Amazon Nova 2 Lite** and **Anthropic Claude Sonnet 5** on Amazon Bedrock Converse (`bytes` or `s3Location` where supported).

Sources (AWS docs):
- [Nova 2 multimodal understanding](https://docs.aws.amazon.com/nova/latest/nova2-userguide/using-multimodal-models.html)
- [Claude Sonnet 5 model card](https://docs.aws.amazon.com/bedrock/latest/userguide/model-card-anthropic-claude-sonnet-5.html)
- [Converse API content blocks](https://docs.aws.amazon.com/bedrock/latest/userguide/conversation-inference.html)
- [Claude document example](https://docs.aws.amazon.com/bedrock/latest/userguide/bedrock-runtime_example_bedrock-runtime_DocumentUnderstanding_AnthropicClaude_section.html)

Model IDs (examples):
- Nova 2 Lite: `global.amazon.nova-2-lite-v1:0` (or geo / foundation IDs)
- Claude Sonnet 5: `global.anthropic.claude-sonnet-5` (or `anthropic.claude-sonnet-5` / geo IDs)

---

## Quick comparison

| Modality (Converse block) | Nova 2 Lite | Claude Sonnet 5 |
| --- | --- | --- |
| Text | Yes | Yes |
| Image | Yes | Yes |
| Document | Yes | Yes |
| Video | Yes | **No** |
| Audio / Speech | Yes (Nova speech capability noted in Nova docs) | **No** |

---

## Amazon Nova 2 Lite

### Image (`image` block)

| Format | Converse `format` value |
| --- | --- |
| PNG | `png` |
| JPEG / JPG | `jpeg` |
| GIF | `gif` (animated: first frame only) |
| WebP | `webp` (animated: first frame only) |

**Input:** request bytes (Converse) or **S3 URI**  
**Limits (summary):** ~25 MB embedded / up to ~2 GB total via S3; up to 5 images embedded, many more via S3 (see Nova docs)

**Not native image formats for Converse image block:** SVG, BMP, ICO, PSD, AI, AVIF, APNG, etc.

### Video (`video` block)

| Format | Converse `format` value |
| --- | --- |
| MP4 | `mp4` |
| MOV | `mov` |
| MKV | `mkv` |
| WebM | `webm` |
| FLV | `flv` |
| MPEG / MPG | `mpeg` |
| WMV | `wmv` |
| 3GP | `three_gp` |

**Input:** request bytes or **S3 URI**  
**Limits (summary):** ~25 MB embedded / ~1 GB via S3; typically 1 video per request  
**Note:** Nova video understanding is visual-frame based; audio tracks are not analyzed.

### Document (`document` block)

| Format | Converse `format` value | Notes |
| --- | --- | --- |
| PDF | `pdf` | Media/layout understanding; CMYK / SVG-in-PDF not supported |
| DOC | `doc` | Text-oriented |
| DOCX | `docx` | Media/layout understanding |
| XLS | `xls` | Spreadsheet |
| XLSX | `xlsx` | Spreadsheet |
| CSV | `csv` | Text/structured |
| TXT | `txt` | Plain text |
| HTML | `html` | Markup |
| Markdown | `md` | Markup |

**Input:** request bytes or **S3 URI**  
**Limits (summary):** up to 5 documents per request; text docs ≤ ~4.5 MB each; PDF/DOCX larger when using S3 (see Nova docs)

---

## Anthropic Claude Sonnet 5

### Modalities (from model card)

| Input | Supported? |
| --- | --- |
| Text | Yes |
| Image | Yes |
| Video | **No** |
| Audio / Speech | **No** |

### Image (`image` block) — Converse / Claude multimodal

| Format | Converse `format` value |
| --- | --- |
| PNG | `png` |
| JPEG / JPG | `jpeg` |
| GIF | `gif` |
| WebP | `webp` |

Same Converse image formats used across Claude on Bedrock.

### Document (`document` block) — Converse

AWS Claude document examples list:

| Format | Converse `format` value |
| --- | --- |
| PDF | `pdf` |
| DOC | `doc` |
| DOCX | `docx` |
| XLS | `xls` |
| XLSX | `xlsx` |
| CSV | `csv` |
| HTML | `html` |
| TXT | `txt` |
| Markdown | `md` |

**Video:** not supported on Claude Sonnet 5 (model card).

**Note:** For PDFs with charts/layouts on Claude, Bedrock may require citations / vision-related document options depending on model generation — check current Converse `document` + `citations` docs when enabling visual PDF understanding.

---

## Portal upload types vs native Converse

Your portal currently accepts more extensions than Converse natively understands. Map carefully:

| Portal accepts | Nova 2 Lite native? | Claude Sonnet 5 native? | Suggested handling |
| --- | --- | --- | --- |
| `pdf`, `doc`, `docx`, `xls`, `xlsx`, `csv`, `txt` | Yes (document) | Yes (document) | Pass via Converse `document` (+ S3 OK) |
| `html`, `md` | Yes (document) | Yes (document) | Pass via Converse `document` |
| `png`, `jpg`, `jpeg`, `gif`, `webp` | Yes (image) | Yes (image) | Pass via Converse `image` |
| `mp4`, `mov`, `mkv`, `webm`, `wmv`, `m4v`, `avi` | Partial (video; not all like `avi`/`m4v`) | No | Nova: only listed video formats; Claude: extract metadata / frames elsewhere |
| `svg`, `bmp`, `ico`, `psd`, `ai`, `apng`, `avif` | No (as image block) | No | Convert or metadata-only |
| `ppt`, `pptx`, `pps`, `ods`, `odt`, `rtf`, `wpd` | No (document block) | No | Extract text elsewhere or convert to PDF |

---

## Practical takeaway for this project

- **Nova 2 Lite** — best fit if you need **documents + images + video** via Converse / S3.
- **Claude Sonnet 5** — strong for **text + images + documents**; **no video** on Converse.
- Prefer Converse formats above when using `s3Location` or bytes; unsupported types need conversion or custom extraction first.
