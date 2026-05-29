<?php
// Desativa a exibição de avisos, notificações e mensagens 'Deprecated' que quebram o layout ou o JSON
error_reporting(0);
ini_set('display_errors', 0);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$baseURL = $protocol . "://" . $host;

define('HOST', $baseURL);

function fetchHTML($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // Simula um navegador real para passar pela proteção do Cloudflare na nuvem do Render
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

function extractSearchResults($html) {
    if (empty($html)) return [];
    
    $dom = new DOMDocument();
    
    // Evita que o leitor de HTML jogue erros na tela por causa de tags modernas do HTML5
    libxml_use_internal_errors(true);
    // Força o carregamento usando a tag XML de UTF-8 para não bugar a acentuação dos títulos dos animes
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    $results = [];
    
    // Captura os blocos de cards dos animes do site alvo
    $cards = $xpath->query("//div[contains(@class, 'divCardUltimosEps')]");

    foreach ($cards as $card) {
        $link = $xpath->query(".//a", $card)->item(0);
        $image = $xpath->query(".//img", $card)->item(0);
        $title = $xpath->query(".//h3[@class='animeTitle']", $card)->item(0);

        if ($link && $image && $title) {
            $originalHref = $link->getAttribute('href');
            
            $results[] = [
                'url' => HOST . "/AnFire_Player.php?link=" . urlencode($originalHref),
                'image' => $image->getAttribute('data-src') ? $image->getAttribute('data-src') : $image->getAttribute('src'),
                'title' => trim($title->nodeValue)
            ];
        }
    }

    return $results;
}

function generateSearchInterface($initialResults = []) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AnFire Browser</title>
        <style>
            body {
                font-family: 'Roboto', Arial, sans-serif;
                background-color: #121212;
                color: #f1f1f1;
                margin: 0;
                padding: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            h1 {
                font-size: 2.8rem;
                margin-bottom: 20px;
                color: #00e6e6;
                text-shadow: 0px 0px 8px rgba(0, 230, 230, 0.7), 0px 0px 15px rgba(0, 140, 140, 0.5);
            }

            .search-container {
                margin-top: 20px;
                text-align: center;
            }

            .search-input {
                padding: 10px;
                width: 300px;
                border-radius: 10px;
                border: 1px solid #555;
                background-color: #1e1e1e;
                color: #fff;
                outline: none;
            }

            .search-button {
                padding: 10px 20px;
                border-radius: 10px;
                border: none;
                background-color: #5288e5;
                color: #000;
                font-weight: bold;
                cursor: pointer;
                transition: background 0.2s;
            }

            .search-button:hover {
                background-color: #3b6bb3;
            }

            .results-container {
                margin-top: 20px;
                width: 90%;
                max-width: 1200px;
                text-align: center;
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                justify-content: center;
                padding: 20px;
                border: 1px solid #333;
                border-radius: 10px;
                background-color: #1e1e1e;
                min-height: 200px;
            }

            .result-item {
                display: inline-block;
                width: 200px;
                transition: transform 0.3s, box-shadow 0.3s;
                background: #151515;
                border-radius: 10px;
                overflow: hidden;
                padding-bottom: 10px;
            }

            .result-item img {
                width: 100%;
                height: 280px;
                object-fit: cover;
                border-radius: 10px 10px 0 0;
            }

            .result-item p {
                color: #ccc;
                font-size: 0.95rem;
                margin: 8px 10px 0 10px;
                line-height: 1.2;
                overflow: hidden;
                text-overflow: ellipsis;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
            }

            .result-item:hover {
                transform: scale(1.04);
                box-shadow: 0px 5px 15px rgba(0,0,0,0.5);
            }

            a {
                text-decoration: none;
            }

            footer {
                margin-top: 40px;
                text-align: center;
                padding: 20px;
                background: #1e1e1e;
                color: #ffffff;
                font-size: 14px;
                width: 100%;
            }

            footer a {
                color: #5288e5;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
            }

            footer a:hover {
                text-decoration: underline;
            }

            footer img {
                width: 20px;
                height: 20px;
                margin-right: 10px;
            }

            #loader-background {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.8);
                z-index: 9998;
            }

            #loader {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999;
                font-size: 1.5rem;
                color: #5288e5;
                animation: pulse 1.5s infinite;
            }

            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }

            .home-link {
                display: inline-block;
                margin: 15px 5px;
                font-size: 1rem;
                font-weight: bold;
                color: #5288e5;
                text-decoration: none;
                padding: 5px 15px;
                border: 2px solid #5288e5;
                border-radius: 5px;
                transition: all 0.3s ease;
            }

            .home-link:hover {
                background-color: #5288e5;
                color: #000;
            }
        </style>
        <script>
            async function searchAnimes() {
                const query = document.getElementById('searchQuery').value.trim();
                if (!query) return alert('Digite algo para buscar.');

                const loader = document.getElementById('loader');
                const loaderBackground = document.getElementById('loader-background');
                loaderBackground.style.display = 'block';
                loader.style.display = 'block';

                try {
                    const response = await fetch(`?search=${encodeURIComponent(query)}`);
                    const results = await response.json();

                    const container = document.getElementById('resultsContainer');
                    container.innerHTML = '';
                    
                    if (results.length === 0) {
                        container.innerHTML = '<p style="color: #ff4500; padding: 20px; font-weight: bold;">Nenhum anime encontrado.</p>';
                    } else {
                        results.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'result-item';
                            div.innerHTML = `
                                <a href="${item.url}">
                                    <img src="${item.image}" alt="${item.title}" onerror="this.src='https://via.placeholder.com/200x280?text=Sem+Imagem'">
                                    <p>${item.title}</p>
                                </a>
                            `;
                            container.appendChild(div);
                        });
                    }
                } catch (error) {
                    alert('Ocorreu um erro ao processar a busca de animes.');
                } finally {
                    loader.style.display = 'none';
                    loaderBackground.style.display = 'none';
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const searchInput = document.getElementById('searchQuery');
                searchInput.addEventListener('keypress', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        searchAnimes();
                    }
                });
            });
        </script>
    </head>
    <body>
        <div id="loader-background"></div>
        <div id="loader">Buscando animes...</div>
        
        <br>
        <img src="https://i.imgur.com/YFFnp7E.png" width="200" alt="AnFire Logo">
        
        <div class="search-container">
            <input type="text" id="searchQuery" class="search-input" placeholder="Buscar animes...">
            <button class="search-button" onclick="searchAnimes()">Buscar</button>
            <br>
            <a href='index.php' class='home-link'>Home</a>
        </div>
        
        <br>
        <div id="resultsContainer" class="results-container">
            <?php if (empty($initialResults)): ?>
                <p style="color: #bbb; padding: 20px;">Nenhum anime atualizado recentemente ou erro de conexão com a fonte.</p>
            <?php else: ?>
                <?php foreach ($initialResults as $item): ?>
                    <div class="result-item">
                        <a href="<?= $item['url'] ?>">
                            <img src="<?= $item['image'] ?>" alt="<?= $item['title'] ?>" onerror="this.src='https://via.placeholder.com/200x280?text=Sem+Imagem'">
                            <p><?= $item['title'] ?></p>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <footer>
            <a href="https://github.com/MestreTM/AnFireAPI/" target="_blank">
                <img src="https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png" alt="GitHub Logo"> AnFireAPI - ver projeto no GitHub.
            </a>
        </footer>
    </body>
    </html>
    <?php
}

// Intercepta a requisição AJAX enviada pelo JavaScript para fazer buscas
if (isset($_GET['search'])) {
    $searchTerm = $_GET['search'];
    $searchUrl = "https://animefire.plus/pesquisar/" . urlencode(strtolower($searchTerm));
    $html = fetchHTML($searchUrl);
    $results = extractSearchResults($html);

    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// Execução padrão ao entrar na página inicial (Lista os animes atualizados)
$html = fetchHTML("https://animefire.plus/animes-atualizados");
$initialResults = extractSearchResults($html);
generateSearchInterface($initialResults);
?>
