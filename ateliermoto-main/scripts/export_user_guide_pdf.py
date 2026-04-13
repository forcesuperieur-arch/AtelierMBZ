from __future__ import annotations

import re
import sys
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import Image, ListFlowable, ListItem, PageBreak, Paragraph, Preformatted, SimpleDocTemplate, Spacer, Table, TableStyle


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_SOURCE = ROOT / "docs" / "GUIDE_UTILISATEUR.md"
DEFAULT_TARGET = ROOT / "docs" / "GUIDE_UTILISATEUR.pdf"
IMAGE_RE = re.compile(r"!\[([^\]]*)\]\(([^)]+)\)")


def register_fonts() -> None:
    pdfmetrics.registerFont(TTFont("DejaVuSans", "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"))
    pdfmetrics.registerFont(TTFont("DejaVuSans-Bold", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"))
    pdfmetrics.registerFont(TTFont("DejaVuSansMono", "/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf"))


def build_styles():
    styles = getSampleStyleSheet()
    styles.add(
        ParagraphStyle(
            name="GuideTitle",
            parent=styles["Title"],
            fontName="DejaVuSans-Bold",
            fontSize=24,
            leading=28,
            alignment=TA_CENTER,
            textColor=colors.HexColor("#1f2937"),
            spaceAfter=6,
        )
    )
    styles.add(
        ParagraphStyle(
            name="GuideSubtitle",
            parent=styles["Normal"],
            fontName="DejaVuSans",
            fontSize=11,
            leading=15,
            alignment=TA_CENTER,
            textColor=colors.HexColor("#4b5563"),
            spaceAfter=16,
        )
    )
    styles.add(
        ParagraphStyle(
            name="GuideHeading1",
            parent=styles["Heading1"],
            fontName="DejaVuSans-Bold",
            fontSize=18,
            leading=22,
            textColor=colors.HexColor("#111827"),
            spaceBefore=16,
            spaceAfter=10,
        )
    )
    styles.add(
        ParagraphStyle(
            name="GuideHeading2",
            parent=styles["Heading2"],
            fontName="DejaVuSans-Bold",
            fontSize=13,
            leading=17,
            textColor=colors.HexColor("#1f2937"),
            spaceBefore=12,
            spaceAfter=6,
        )
    )
    styles.add(
        ParagraphStyle(
            name="GuideHeading3",
            parent=styles["Heading3"],
            fontName="DejaVuSans-Bold",
            fontSize=11,
            leading=14,
            textColor=colors.HexColor("#374151"),
            spaceBefore=8,
            spaceAfter=4,
        )
    )
    styles.add(
        ParagraphStyle(
            name="GuideBody",
            parent=styles["Normal"],
            fontName="DejaVuSans",
            fontSize=9.5,
            leading=13,
            alignment=TA_LEFT,
            textColor=colors.HexColor("#1f2937"),
            spaceAfter=3,
        )
    )
    styles.add(
        ParagraphStyle(
            name="GuideQuote",
            parent=styles["Normal"],
            fontName="DejaVuSans",
            fontSize=9.5,
            leading=13,
            leftIndent=10,
            borderPadding=8,
            borderColor=colors.HexColor("#d1d5db"),
            borderWidth=0.8,
            borderRadius=4,
            backColor=colors.HexColor("#f9fafb"),
            textColor=colors.HexColor("#374151"),
            spaceBefore=4,
            spaceAfter=8,
        )
    )
    styles.add(
        ParagraphStyle(
            name="GuideCode",
            parent=styles["Code"],
            fontName="DejaVuSansMono",
            fontSize=8.5,
            leading=11,
            textColor=colors.HexColor("#111827"),
            backColor=colors.HexColor("#f3f4f6"),
            borderPadding=8,
            borderColor=colors.HexColor("#e5e7eb"),
            borderWidth=0.8,
            borderRadius=4,
            leftIndent=4,
            rightIndent=4,
            spaceBefore=4,
            spaceAfter=8,
        )
    )
    styles.add(
        ParagraphStyle(
            name="GuideCaption",
            parent=styles["Normal"],
            fontName="DejaVuSans",
            fontSize=8,
            leading=10,
            alignment=TA_CENTER,
            textColor=colors.HexColor("#6b7280"),
            spaceBefore=2,
            spaceAfter=8,
        )
    )
    styles.add(
        ParagraphStyle(
            name="GuideFooter",
            parent=styles["Normal"],
            fontName="DejaVuSans",
            fontSize=8,
            leading=10,
            alignment=TA_CENTER,
            textColor=colors.HexColor("#6b7280"),
        )
    )
    return styles


def escape_text(text: str) -> str:
    return (
        text.replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
    )


def inline_markup(text: str) -> str:
    escaped = escape_text(text)
    escaped = re.sub(r"`([^`]+)`", r"<font name='DejaVuSansMono'>\1</font>", escaped)
    escaped = re.sub(r"\*\*([^*]+)\*\*", r"<b>\1</b>", escaped)
    return escaped


def build_table(lines, styles):
    rows = []
    for line in lines:
        if set(line.replace("|", "").replace(" ", "").replace("-", "")):
            parts = [part.strip() for part in line.strip().strip("|").split("|")]
            rows.append([Paragraph(inline_markup(cell), styles["GuideBody"]) for cell in parts])
    if not rows:
        return None
    table = Table(rows, repeatRows=1, hAlign="LEFT")
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#111827")),
                ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                ("FONTNAME", (0, 0), (-1, 0), "DejaVuSans-Bold"),
                ("FONTSIZE", (0, 0), (-1, -1), 8.8),
                ("LEADING", (0, 0), (-1, -1), 11),
                ("BACKGROUND", (0, 1), (-1, -1), colors.HexColor("#f9fafb")),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.HexColor("#ffffff"), colors.HexColor("#f9fafb")]),
                ("GRID", (0, 0), (-1, -1), 0.6, colors.HexColor("#d1d5db")),
                ("LEFTPADDING", (0, 0), (-1, -1), 6),
                ("RIGHTPADDING", (0, 0), (-1, -1), 6),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ]
        )
    )
    return table


def build_markdown_image(line: str, styles, base_dir: Path):
    match = IMAGE_RE.fullmatch(line.strip())
    if not match:
        return None

    alt_text = match.group(1).strip() or "Capture d'ecran"
    image_path = (base_dir / match.group(2).strip()).resolve()
    if not image_path.exists():
        return [Paragraph(inline_markup(f"[Image introuvable: {image_path.name}]"), styles["GuideQuote"])]

    max_width = 170 * mm
    max_height = 105 * mm
    img_reader = ImageReader(str(image_path))
    width, height = img_reader.getSize()
    scale = min(max_width / width, max_height / height, 1)

    flowable = Image(
        str(image_path),
        width=width * scale,
        height=height * scale,
        hAlign="CENTER",
    )
    caption = Paragraph(inline_markup(alt_text), styles["GuideCaption"])
    return [flowable, caption]


def collect_list(lines, ordered):
    items = []
    prefix_re = re.compile(r"^\s*\d+\.\s+") if ordered else re.compile(r"^\s*-\s+")
    for line in lines:
        items.append(prefix_re.sub("", line).strip())
    return items


def parse_markdown(markdown_text: str, styles, base_dir: Path):
    story = []
    lines = markdown_text.splitlines()
    index = 0
    in_title_block = True

    while index < len(lines):
        line = lines[index].rstrip()
        stripped = line.strip()

        if in_title_block and stripped.startswith("# "):
            story.append(Spacer(1, 8))
            story.append(Paragraph(inline_markup(stripped[2:].strip()), styles["GuideTitle"]))
            index += 1
            continue
        if in_title_block and stripped.startswith("> "):
            story.append(Paragraph(inline_markup(stripped[2:].strip()), styles["GuideSubtitle"]))
            index += 1
            continue
        in_title_block = False

        if not stripped:
            story.append(Spacer(1, 4))
            index += 1
            continue

        if stripped == "---":
            story.append(Spacer(1, 6))
            index += 1
            continue

        if stripped.startswith("```"):
            code_lines = []
            index += 1
            while index < len(lines) and not lines[index].strip().startswith("```"):
                code_lines.append(lines[index].rstrip("\n"))
                index += 1
            story.append(Preformatted("\n".join(code_lines), styles["GuideCode"]))
            index += 1
            continue

        image_flowables = build_markdown_image(stripped, styles, base_dir)
        if image_flowables is not None:
            story.extend(image_flowables)
            index += 1
            continue

        if stripped.startswith("## "):
            story.append(Paragraph(inline_markup(stripped[3:].strip()), styles["GuideHeading1"]))
            index += 1
            continue

        if stripped.startswith("### "):
            story.append(Paragraph(inline_markup(stripped[4:].strip()), styles["GuideHeading2"]))
            index += 1
            continue

        if stripped.startswith("#### "):
            story.append(Paragraph(inline_markup(stripped[5:].strip()), styles["GuideHeading3"]))
            index += 1
            continue

        if stripped.startswith("> "):
            story.append(Paragraph(inline_markup(stripped[2:].strip()), styles["GuideQuote"]))
            index += 1
            continue

        if stripped.startswith("|") and stripped.endswith("|"):
            table_lines = []
            while index < len(lines):
                probe = lines[index].rstrip()
                if probe.strip().startswith("|") and probe.strip().endswith("|"):
                    table_lines.append(probe)
                    index += 1
                    continue
                break
            table = build_table(table_lines, styles)
            if table is not None:
                story.append(table)
                story.append(Spacer(1, 6))
            continue

        if re.match(r"^\s*-\s+", line):
            list_lines = []
            while index < len(lines) and re.match(r"^\s*-\s+", lines[index]):
                list_lines.append(lines[index].rstrip())
                index += 1
            items = [ListItem(Paragraph(inline_markup(item), styles["GuideBody"])) for item in collect_list(list_lines, ordered=False)]
            story.append(ListFlowable(items, bulletType="bullet", start="circle", leftIndent=14))
            story.append(Spacer(1, 4))
            continue

        if re.match(r"^\s*\d+\.\s+", line):
            list_lines = []
            while index < len(lines) and re.match(r"^\s*\d+\.\s+", lines[index]):
                list_lines.append(lines[index].rstrip())
                index += 1
            items = [ListItem(Paragraph(inline_markup(item), styles["GuideBody"])) for item in collect_list(list_lines, ordered=True)]
            story.append(ListFlowable(items, bulletType="1", leftIndent=14))
            story.append(Spacer(1, 4))
            continue

        paragraph_lines = [stripped]
        index += 1
        while index < len(lines):
            probe = lines[index].strip()
            if not probe:
                break
            if probe.startswith(("#", ">", "|", "```")):
                break
            if re.match(r"^\s*-\s+", lines[index]) or re.match(r"^\s*\d+\.\s+", lines[index]):
                break
            if probe == "---":
                break
            paragraph_lines.append(probe)
            index += 1
        story.append(Paragraph(inline_markup(" ".join(paragraph_lines)), styles["GuideBody"]))

    return story


def add_page_canvas(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(colors.HexColor("#d1d5db"))
    canvas.setLineWidth(0.6)
    canvas.line(doc.leftMargin, A4[1] - 18 * mm, A4[0] - doc.rightMargin, A4[1] - 18 * mm)
    canvas.setFont("DejaVuSans", 8)
    canvas.setFillColor(colors.HexColor("#6b7280"))
    canvas.drawString(doc.leftMargin, 10 * mm, "Guide Utilisateur - Atelier Moto Pro")
    canvas.drawRightString(A4[0] - doc.rightMargin, 10 * mm, f"Page {canvas.getPageNumber()}")
    canvas.restoreState()


def generate_pdf(source: Path, target: Path) -> None:
    register_fonts()
    styles = build_styles()
    markdown_text = source.read_text(encoding="utf-8")
    story = parse_markdown(markdown_text, styles, source.parent)

    doc = SimpleDocTemplate(
        str(target),
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=24 * mm,
        bottomMargin=18 * mm,
        title="Guide Utilisateur - Atelier Moto Pro",
        author="GitHub Copilot",
        subject="Guide utilisateur",
    )
    doc.build(story, onFirstPage=add_page_canvas, onLaterPages=add_page_canvas)


def main(argv: list[str]) -> int:
    source = Path(argv[1]).resolve() if len(argv) > 1 else DEFAULT_SOURCE
    target = Path(argv[2]).resolve() if len(argv) > 2 else DEFAULT_TARGET
    generate_pdf(source, target)
    print(target)
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))