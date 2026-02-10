# SynapseWP - AI Assistant for WordPress

**SynapseWP** is a comprehensive AI integration for WordPress that enhances your writing workflow, automates content organization, optimizes SEO, and provides powerful content tools using Google's Gemini API.

## 🚀 Features

### AI Writing Assistant

- **Content Expansion**: Instantly turn rough ideas into professional paragraphs
- **Conversational Chat**: Interact with AI in a natural chat interface
- **Writer Mode**: Generate complete article sections with automatic title suggestions
- **Smart Context**: Understands your prompt and generates relevant, high-quality content

### Content Tools

- **Summarization**: Create concise summaries (short, medium, or long)
- **Paraphrasing**: Rewrite content in different tones (professional, casual, academic)
- **Content Improvement**: Fix grammar, enhance clarity, and improve flow
- **Simplification**: Make complex content more concise

### Translation & Multilingual Support

- **Instant Translation**: Translate to 12+ languages including Spanish, French, German, Portuguese, Italian, Japanese, Chinese, Arabic, Hindi, Russian, and Korean
- **Maintain Formatting**: Translations preserve your content structure

### SEO Optimization

- **Meta Description Generation**: AI-powered SEO-optimized meta descriptions (150-160 characters)
- **Focus Keywords**: Automatic keyword extraction and suggestions
- **SEO Score**: Instant SEO assessment with improvement suggestions

### Image Optimization

- **Auto Alt-Text Generation**: Automatically generate descriptive alt text when uploading images
- **Manual Generation**: Generate alt text for existing images on demand
- **Bulk Processing**: Process multiple images at once
- **Caption Suggestions**: AI-generated captions for better image context

### Content Templates

- **FAQ Generation**: Automatically create FAQ sections from your content
- **Bullet-Point Summaries**: Extract key points as organized lists

### Auto-Categorization

- **Smart Tagging**: Automatically assigns relevant categories upon publication

## Installation

1. Upload `synapsewp` folder to `/wp-content/plugins/`
2. Activate through 'Plugins' menu
3. Navigate to **SynapseWP** in admin menu to configure

## Configuration

1. Go to **SynapseWP** settings
2. Enter your **Google Gemini API Key** - [Get one here](https://makersuite.google.com/app/apikey)
3. Select AI Model (Gemini 2.5 Flash Lite for speed, Pro for quality)
4. Set Max Categories, Default Language, and Auto Alt-Text preferences
5. Save Changes

## Usage

### Writing Assistant

- Navigate to **Chat** tab for conversational assistance
- Use **Writer Mode** for full article generation
- Click quick template chips for instant prompts

### Content Tools

- Go to **Tools** tab
- Select text in editor
- Click desired tool (Summarize, Paraphrase, Improve, Simplify)
- For translation: choose target language and click Translate

### SEO Meta

- Navigate to **SEO** tab
- Click "Generate Meta Description"
- Review generated meta, keywords, score, and suggestions
- Click Copy to use in your SEO plugin

### Image Alt-Text

- **Auto**: Enable in settings, upload images as normal
- **Manual**: In Media Library, click "Generate with AI"
- **Bulk**: Use REST API `/wp-json/synapsewp/v1/bulk-alt-text`

### Templates

- Navigate to **Templates** tab
- Click "Generate FAQ" or "Bullet Summary"

## 📋 Requirements

- WordPress 5.0+
- PHP 7.4+
- Google Cloud API Key (Gemini)

## 📄 License

GPLv2 or later.

## 📦 Changelog

### 1.2.0

- **New**: Content Rewriting Tools (Summarize, Paraphrase, Improve, Simplify)
- **New**: Translation Support (12+ languages)
- **New**: SEO Meta Generation (descriptions, keywords, scoring)
- **New**: Image Alt-Text Generation (auto, manual, bulk)
- **New**: Content Templates (FAQ, Bullet Summaries)
- **New**: Tabbed UI (Chat, Tools, SEO, Templates)
- **Enhancement**: Language Settings & Translation Preferences
- **Enhancement**: Vision AI Support
- **Enhancement**: Localization Ready

### 1.1.0

- **New Feature**: Conversational Chat Interface! Replaced the simple text box with a full chat history UI.
- **New Feature**: Quick Prompt Templates (Headlines, Summarize, Fix Grammar, Intro).
- **New Feature**: Native WordPress Block Support - AI content is now inserted as proper Gutenberg blocks (paragraphs, headings, lists) instead of HTML.
- **Enhancement**: Typing indicators for better UX.
- **Enhancement**: Smart HTML-to-text conversion for Classic Editor compatibility.
- **Optimization**: Moved assets to dedicated CSS/JS files.
- **Fix**: Improved JSON parsing with regex fallback for Writer Mode.

### 1.0.3

- **Fix**: Enforce Max Categories limit during auto-assignment.

### 1.0.2

- **New Feature**: Writer Mode! Now the AI can write directly into your post editor (compatible with Classic & Gutenberg).
- **New Feature**: Automatic Title Generation in Writer Mode for SEO optimization.

### 1.0.1

- **Enhancement**: Added "Max Categories" setting to control auto-categorization limits.
- **Fix**: Improved categorization logic to exclude generic terms (e.g., "Uncategorized").
- **Fix**: Automatically removes the default "Uncategorized" category when new categories are assigned.
- **Improvement**: Increased AI context window for better accuracy.
