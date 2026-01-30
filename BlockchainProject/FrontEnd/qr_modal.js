// QR Code Modal and History Display
function showQRHistory(productId, productName) {
  fetch(`qr_history.php?id=${productId}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        alert('Error: ' + data.error);
        return;
      }
      
      // Create modal
      const modal = document.createElement('div');
      modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.8);display:flex;align-items:center;justify-content:center;z-index:9999;';
      
      const content = document.createElement('div');
      content.style.cssText = 'background:#1f2937;border:1px solid #444;border-radius:16px;padding:30px;max-width:600px;max-height:80vh;overflow-y:auto;color:#e5e7eb;';
      
      // Build history HTML
      let historyHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
          <h2 style="margin:0;">Product #${data.id}: ${data.name}</h2>
          <button onclick="this.closest('[role=dialog]').remove()" style="background:#ef4444;color:white;border:0;padding:8px 16px;border-radius:6px;cursor:pointer;">✕</button>
        </div>
        
        <div style="background:rgba(255,255,255,.05);padding:15px;border-radius:8px;margin:15px 0;">
          <h3 style="margin-top:0;margin-bottom:12px;color:#22d3ee;">📊 Product Details</h3>
          <table style="width:100%;border-collapse:collapse;">
            <tr style="border-bottom:1px solid #444;">
              <td style="padding:8px;color:#94a3b8;">Price:</td>
              <td style="padding:8px;font-weight:bold;color:#86efac;">$${parseFloat(data.price).toFixed(2)}</td>
            </tr>
            <tr style="border-bottom:1px solid #444;">
              <td style="padding:8px;color:#94a3b8;">Quantity:</td>
              <td style="padding:8px;font-weight:bold;color:#86efac;">${data.quantity}</td>
            </tr>
            <tr style="border-bottom:1px solid #444;">
              <td style="padding:8px;color:#94a3b8;">Status:</td>
              <td style="padding:8px;font-weight:bold;color:#86efac;">${data.status}</td>
            </tr>
            <tr>
              <td style="padding:8px;color:#94a3b8;">Updated:</td>
              <td style="padding:8px;font-weight:bold;color:#86efac;">${data.updated_at}</td>
            </tr>
          </table>
        </div>
        
        <div style="background:rgba(255,255,255,.05);padding:15px;border-radius:8px;margin:15px 0;">
          <h3 style="margin-top:0;margin-bottom:12px;color:#a78bfa;">🔗 Supply Chain History</h3>
          <table style="width:100%;border-collapse:collapse;font-size:13px;">
      `;
      
      // Producer (Owner)
      if (data.owner) {
        historyHTML += `
            <tr style="border-bottom:1px solid #444;">
              <td style="padding:8px;color:#94a3b8;font-weight:bold;">👨‍🌾 Producer:</td>
              <td style="padding:8px;">${data.owner}</td>
            </tr>
        `;
        if (data.owner_tx) {
          historyHTML += `
            <tr style="border-bottom:1px solid #444;">
              <td style="padding:8px;color:#94a3b8;">Tx:</td>
              <td style="padding:8px;"><a href="https://sepolia.etherscan.io/tx/${data.owner_tx}" target="_blank" style="color:#22d3ee;text-decoration:none;">${data.owner_tx.substring(0, 10)}…</a></td>
            </tr>
          `;
        }
      }
      
      // Supplier
      if (data.supplier && data.supplier !== 'producer') {
        historyHTML += `
            <tr style="border-bottom:1px solid #444;">
              <td style="padding:8px;color:#94a3b8;font-weight:bold;">🚚 Supplier:</td>
              <td style="padding:8px;">${data.supplier}</td>
            </tr>
        `;
        if (data.supplier_tx) {
          historyHTML += `
            <tr style="border-bottom:1px solid #444;">
              <td style="padding:8px;color:#94a3b8;">Tx:</td>
              <td style="padding:8px;"><a href="https://sepolia.etherscan.io/tx/${data.supplier_tx}" target="_blank" style="color:#22d3ee;text-decoration:none;">${data.supplier_tx.substring(0, 10)}…</a></td>
            </tr>
          `;
        }
      }
      
      // Consumer
      if (data.consumer) {
        historyHTML += `
            <tr style="border-bottom:1px solid #444;">
              <td style="padding:8px;color:#94a3b8;font-weight:bold;">👤 Consumer:</td>
              <td style="padding:8px;">${data.consumer}</td>
            </tr>
        `;
      }
      
      historyHTML += `
          </table>
        </div>
      `;
      
      content.innerHTML = historyHTML;
      content.setAttribute('role', 'dialog');
      modal.appendChild(content);
      document.body.appendChild(modal);
      
      // Close on modal background click
      modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
      });
    })
    .catch(err => {
      console.error(err);
      alert('Failed to load product history');
    });
}

// Wire QR code clicks (call this after DOM is ready)
function wireQRCodeClicks() {
  document.querySelectorAll('img[data-qr-product]').forEach(img => {
    img.style.cursor = 'pointer';
    img.addEventListener('click', function() {
      const productId = this.dataset.qrProduct;
      const productName = this.dataset.qrName;
      showQRHistory(productId, productName);
    });
  });
}

window.addEventListener('load', wireQRCodeClicks);
