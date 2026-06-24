<?php
/*
Plugin Name: Generate QR Code
Plugin URI: https://github.com/SachinSAgrawal/YOURLS-Generate-QRCode
Description: Shows a customizable QR code generator directly upon link generation and afterwards
Version: 1.0
Author: Sachin Agrawal
Author URI: https://sachinsagrawal.github.io/
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

// Register a shutdown function to inject scripts at the very end of the page load and ignore admin/API paths
if ( !yourls_is_admin() && !yourls_is_API() ) {
    register_shutdown_function('qr_plugin_inject_shutdown');
}

// Kick in if the loader does not recognize a valid pattern
yourls_add_action('redirect_keyword_not_found', 'qr_plugin_catch_dot_qr', 1);

function qr_plugin_catch_dot_qr( $request ) {
    // Get authorized charset in keywords and make a regexp pattern
    $pattern = yourls_make_regexp_pattern( yourls_get_shorturl_charset() );

    // Check if the request ends with .qr
    if( preg_match( "@^([$pattern]+)\.qr?/?$@", $request[0], $matches ) ) {
        $keyword = yourls_sanitize_keyword( $matches[1] );
        
        // Check if the keyword is a valid short URL
        if( yourls_is_shorturl( $keyword ) ) {
            $shortUrl = YOURLS_SITE . '/' . $keyword;
            
            // If so display the QR code generator interface
            yourls_html_head( 'qr_code_generator', 'QR Code Customizer' );
            
            ?>
            <div class="sub_wrap">
                <h2>QR Code Customizer</h2>
                <p>URL: <a href="<?php echo htmlspecialchars($shortUrl, ENT_QUOTES); ?>"><?php echo htmlspecialchars($shortUrl, ENT_QUOTES); ?></a></p>
                
                <div id="qr-standalone-container" data-shorturl="<?php echo htmlspecialchars($shortUrl, ENT_QUOTES); ?>"></div>
            </div>
            <?php
            
            qr_plugin_print_scripts();
            
            yourls_html_footer();
            exit;
        }
    }
}

function qr_plugin_inject_shutdown() {
    // Ensure we are processing the main index page to avoid injecting on side pages
    if ( basename($_SERVER['SCRIPT_FILENAME']) !== 'index.php' ) return;
    qr_plugin_print_scripts();
}

function qr_plugin_print_scripts() {
    ?>
    <script src="<?php echo yourls_plugin_url( dirname( __FILE__ ) ) . '/qrcode.js'; ?>"></script>
    <script>
    // Self-executing anonymous function runs immediately
    (function() {
        const standaloneContainer = document.getElementById('qr-standalone-container');
        const copyButton = document.getElementById('copy-button');

        let targetContainer, shortUrl, isStandalone;

        if (standaloneContainer) {
            targetContainer = standaloneContainer;
            shortUrl = standaloneContainer.getAttribute('data-shorturl');
            isStandalone = true;
        } else if (copyButton) {
            // Find parent container to append our QR interface
            targetContainer = copyButton.closest('.row.justify-content-center .col-10');
            if (!targetContainer) return;
            // Find the shortened URL text input field or copy button
            shortUrl = copyButton.getAttribute('data-shorturl');
            isStandalone = false;
        } else {
            return; 
        }

        if (!shortUrl) return;

        // Define options data once to avoid coding dropdowns twice
        const formGroups = [
            { id: 'qr-ecc', label: 'Error Correction', html: '<option value="L">Low (~7%)</option><option value="M" selected>Medium (~15%)</option><option value="Q">Quartile (~25%)</option><option value="H">High (~30%)</option>' },
            { id: 'qr-version', label: 'QR Version', html: '<option value="0" selected>Auto-Detect</option>' + Array.from({length: 10}, (_, i) => `<option value="${i+1}">Version ${i+1}</option>`).join('') },
            { id: 'qr-filetype', label: 'File Type', html: '<option value="png" selected>PNG</option><option value="jpg">JPG</option><option value="svg">SVG</option>' },
            { id: 'qr-resolution', label: 'Resolution', html: '<option value="12">Low</option><option value="24" selected>Medium</option><option value="48">High</option>' },
            { id: 'qr-padding', label: 'Padding', html: '<option value="1">1 Square</option><option value="2" selected>2 Squares</option><option value="3">3 Squares</option><option value="4">4 Squares</option>' }
        ];

        // Create the QR Code Control Interface elements
        const qrWrapper = document.createElement('div');
        
        if (isStandalone) {
            // Native single column layout for standalone page
            let controlsHtml = '';
            formGroups.forEach(group => {
                controlsHtml += `
                    <div style="margin-bottom: 15px; text-align: left;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">${group.label}</label>
                        <select id="${group.id}" style="width:150px; height:26px; box-sizing:border-box; margin-left:0px;">${group.html}</select>
                    </div>
                `;
            });

            qrWrapper.innerHTML = `
                <div style="text-align: left; margin-bottom: 20px;">
                    ${controlsHtml}
                    <input type="button" class="button button-primary" id="download-button" value="Download QR Code" style="margin-top:5px; margin-left:0px; cursor:pointer;">
                </div>
                
                <style>
                    #qrcode-display {
                        max-width: 70%; 
                    }
                    @media (min-width: 900px) {
                        #qrcode-display {
                            max-width: 50%;
                        }
                    }
                    @media (min-width: 1201px) {
                        #qrcode-display {
                            max-width: 30%;
                        }
                    }
                </style>
                <div id="qrcode-display" style="padding-bottom:20px; text-align:left;"></div>
            `;
        } else {
            // Bootstrap grid layout for the main page
            qrWrapper.className = 'mt-4 pt-3 border-top text-center text-dark';
            
            const renderCol = (group, classes) => `
                <div class="col-4 text-start ${classes}">
                    <label class="form-label small mb-1">${group.label}</label>
                    <select id="${group.id}" class="form-select">${group.html}</select>
                </div>
            `;

            qrWrapper.innerHTML = `
                <div class="row w-100 m-0 mb-3 p-0 align-items-end">
                    ${renderCol(formGroups[0], 'ps-0 pe-1')}
                    ${renderCol(formGroups[1], 'px-1')}
                    ${renderCol(formGroups[2], 'pe-0 ps-1')}
                </div>
                <div class="row w-100 m-0 mb-3 p-0 align-items-end">
                    ${renderCol(formGroups[3], 'ps-0 pe-1')}
                    ${renderCol(formGroups[4], 'px-1')}
                    <div class="col-4 text-end pe-0 ps-1">
                        <input type="button" class="btn btn-primary text-uppercase w-100" id="download-button" value="Download">
                    </div>
                </div>
                <div id="qrcode-display" class="bg-white p-3 d-inline-block rounded my-2" style="border: 2px solid #e0e0e0; max-width: 60%;"></div>
                <br>
                <span class="info" style="display: block; text-align: left; margin-top: 16px;">Edit this QR code later at <a href="${shortUrl}.qr">${shortUrl}.qr</a></span>
            `;
        }

        targetContainer.appendChild(qrWrapper);

        const eccSelect = document.getElementById('qr-ecc');
        const versionSelect = document.getElementById('qr-version');
        const filetypeSelect = document.getElementById('qr-filetype');
        const resolutionSelect = document.getElementById('qr-resolution');
        const paddingSelect = document.getElementById('qr-padding');
        const displayDiv = document.getElementById('qrcode-display');
        const downloadBtn = document.getElementById('download-button');

        function generateQR() {
            try {
                const ecc = eccSelect.value;
                const version = parseInt(versionSelect.value, 10);
                const fileType = filetypeSelect.value;
                const res = parseInt(resolutionSelect.value, 10);
                const pad = parseInt(paddingSelect.value, 10);
                
                const qr = qrcode(version, ecc);
                qr.addData(shortUrl);
                qr.make();
                
                const moduleCount = qr.getModuleCount();
                const size = (moduleCount + pad * 2) * res;

                if (fileType === 'svg') {
                    // Generate an SVG element natively 
                    let svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">`;
                    svg += `<rect width="${size}" height="${size}" fill="#ffffff"/>`;
                    for (let row = 0; row < moduleCount; row++) {
                        for (let col = 0; col < moduleCount; col++) {
                            if (qr.isDark(row, col)) {
                                svg += `<rect x="${(col + pad) * res}" y="${(row + pad) * res}" width="${res}" height="${res}" fill="#000000"/>`;
                            }
                        }
                    }
                    svg += `</svg>`;
                    
                    displayDiv.innerHTML = svg;
                    
                    const svgEl = displayDiv.querySelector('svg');
                    if(svgEl) {
                        svgEl.style.maxWidth = '100%';
                        svgEl.style.height = 'auto';
                    }
                } else {
                    // Generate PNG/JPG utilizing a Canvas so we have strict control over size/padding
                    const canvas = document.createElement('canvas');
                    canvas.width = size;
                    canvas.height = size;
                    const ctx = canvas.getContext('2d');
                    
                    // Force white background for JPG (which doesn't have transparency support)
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, size, size);
                    
                    ctx.fillStyle = '#000000';
                    for (let row = 0; row < moduleCount; row++) {
                        for (let col = 0; col < moduleCount; col++) {
                            if (qr.isDark(row, col)) {
                                ctx.fillRect((col + pad) * res, (row + pad) * res, res, res);
                            }
                        }
                    }
                    
                    const mimeType = fileType === 'jpg' ? 'image/jpeg' : 'image/png';
                    const dataURL = canvas.toDataURL(mimeType);
                    
                    displayDiv.innerHTML = `<img src="${dataURL}" style="max-width: 100%; height: auto;" />`;
                }
            } catch (error) {
                displayDiv.innerHTML = `<span style="color:red; font-size: 0.9em;">Configuration failed:<br>${error}<br><br>Increase version or lower correction level.</span>`;
            }
        }

        // Event listeners to redraw dynamically when user edits parameters
        eccSelect.addEventListener('change', generateQR);
        versionSelect.addEventListener('change', generateQR);
        filetypeSelect.addEventListener('change', generateQR);
        resolutionSelect.addEventListener('change', generateQR);
        paddingSelect.addEventListener('change', generateQR);

        // Download functionality
        downloadBtn.addEventListener('click', function() {
            const fileType = filetypeSelect.value;
            let downloadUrl = '';
            
            if (fileType === 'svg') {
                const svgEl = displayDiv.querySelector('svg');
                if (svgEl) {
                    const svgData = new XMLSerializer().serializeToString(svgEl);
                    const blob = new Blob([svgData], {type: 'image/svg+xml;charset=utf-8'});
                    downloadUrl = URL.createObjectURL(blob);
                }
            } else {
                const imgEl = displayDiv.querySelector('img');
                if (imgEl && imgEl.src) {
                    downloadUrl = imgEl.src;
                }
            }

            if (downloadUrl) {
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = `qrcode.${fileType}`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Cleanup object URL if we generated a blob for the SVG
                if (fileType === 'svg') {
                    URL.revokeObjectURL(downloadUrl);
                }
            }
        });

        // Initial generation run
        generateQR();
    })();
    </script>
    <?php
}