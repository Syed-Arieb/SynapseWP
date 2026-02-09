# SynapseWP - AI Assistant for WordPress

**SynapseWP** is a lightweight, powerful AI integration for WordPress that enhances your writing workflow and automates content organization using Google's Gemini API.

## 🚀 Features

### ✍️ AI Writing Assistant

- **Content Expansion**: Instantly turn rough ideas into professional paragraphs directly within the WordPress editor.
- **Seamless UI**: Integrated "AI Assistant" metabox in the post editor.
- **Smart Context**: Understands your prompt and generates relevant, high-quality content.

### 🗂️ Auto-Categorization

- **Smart Tagging**: Automatically suggests and assigns relevant categories to your posts upon publication.
- **Zero Friction**: Works in the background effectively immediately when you hit "Publish".

## 🛠️ Installation

1. Upload the `synapsewp` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **SynapseWP** in the admin menu to configure your settings.

## ⚙️ Configuration

1. Go to **SynapseWP** in the admin sidebar.
2. Enter your **Google Gemini API Key**.
   - [Get your free API Key here](https://makersuite.google.com/app/apikey).
3. Select your preferred **AI Model** (e.g., Gemini 2.5 Flash Lite for speed, or Pro for quality).
4. Click **Save Changes**.

## 📖 Usage

### Using the Writing Assistant

1. Create or edit a Post.
2. Look for the "SynapseWP AI Assistant" box in the sidebar.
3. Type a brief idea or topic (e.g., "The importance of improved UI in 2026").
4. Click **Expand with AI**.
5. Copy the generated result and use it in your content!

### Auto-Categorization

just **Publish** your post! SynapseWP will analyze your content and automatically assign 3 relevant categories. If the categories don't exist, it will create them for you.

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- A valid Google Cloud API Key (Gemini)

## 📄 License

GPLv2 or later.

## 📦 Changelog

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
