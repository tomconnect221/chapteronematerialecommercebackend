<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | Chapter One</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:type" content="product">
    <meta property="og:site_name" content="Chapter One">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $imageUrl }}">
    <meta property="og:image:alt" content="{{ $title }}">
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta property="product:price:amount" content="{{ $price }}">
    <meta property="product:price:currency" content="NGN">
    @if ($category)
        <meta property="product:category" content="{{ $category }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $imageUrl }}">
</head>
<body>
    <main>
        <img src="{{ $imageUrl }}" alt="{{ $title }}" style="max-width: min(100%, 480px); height: auto">
        <h1>{{ $title }}</h1>
        <p>{{ $description }}</p>
        <p>Price: ₦{{ number_format((float) $price, 2) }}</p>
        <a href="{{ $productUrl }}">View product at Chapter One</a>
    </main>
</body>
</html>