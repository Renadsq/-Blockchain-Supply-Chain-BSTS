// === CONFIG: fill with your deployed contract data ===

const CONTRACT_ADDRESS = "0x945a325DCf0f514A5B8258DDba1E897FCE6ef8E4"; //YOUR SMART CONTRACT ADDRESS GOES HERE

const ABI = [ 
	{
		"anonymous": false,
		"inputs": [
			{
				"indexed": true,
				"internalType": "uint256",
				"name": "productId",
				"type": "uint256"
			},
			{
				"indexed": true,
				"internalType": "address",
				"name": "producer",
				"type": "address"
			},
			{
				"indexed": false,
				"internalType": "uint256",
				"name": "timestamp",
				"type": "uint256"
			},
			{
				"indexed": false,
				"internalType": "bytes32",
				"name": "dataHash",
				"type": "bytes32"
			}
		],
		"name": "ProductRegistered",
		"type": "event"
	},
	{
		"anonymous": false,
		"inputs": [
			{
				"indexed": true,
				"internalType": "uint256",
				"name": "productId",
				"type": "uint256"
			},
			{
				"indexed": true,
				"internalType": "address",
				"name": "from",
				"type": "address"
			},
			{
				"indexed": true,
				"internalType": "address",
				"name": "to",
				"type": "address"
			},
			{
				"indexed": false,
				"internalType": "uint256",
				"name": "timestamp",
				"type": "uint256"
			}
		],
		"name": "ProductTransferred",
		"type": "event"
	},
	{
		"inputs": [
			{
				"internalType": "uint256",
				"name": "_productId",
				"type": "uint256"
			}
		],
		"name": "getProductHistory",
		"outputs": [
			{
				"internalType": "address[]",
				"name": "",
				"type": "address[]"
			}
		],
		"stateMutability": "view",
		"type": "function"
	},
	{
		"inputs": [
			{
				"internalType": "uint256",
				"name": "",
				"type": "uint256"
			}
		],
		"name": "products",
		"outputs": [
			{
				"internalType": "uint256",
				"name": "productId",
				"type": "uint256"
			},
			{
				"internalType": "address",
				"name": "owner",
				"type": "address"
			},
			{
				"internalType": "uint256",
				"name": "timestamp",
				"type": "uint256"
			},
			{
				"internalType": "bytes32",
				"name": "productInfoHash",
				"type": "bytes32"
			},
			{
				"internalType": "bool",
				"name": "exists",
				"type": "bool"
			}
		],
		"stateMutability": "view",
		"type": "function"
	},
	{
		"inputs": [
			{
				"internalType": "uint256",
				"name": "_productId",
				"type": "uint256"
			},
			{
				"internalType": "string",
				"name": "_productData",
				"type": "string"
			}
		],
		"name": "registerProduct",
		"outputs": [],
		"stateMutability": "nonpayable",
		"type": "function"
	},
	{
		"inputs": [
			{
				"internalType": "uint256",
				"name": "_productId",
				"type": "uint256"
			},
			{
				"internalType": "address",
				"name": "_newOwner",
				"type": "address"
			}
		],
		"name": "transferProduct",
		"outputs": [],
		"stateMutability": "nonpayable",
		"type": "function"
	},
	{
		"inputs": [
			{
				"internalType": "uint256",
				"name": "_productId",
				"type": "uint256"
			},
			{
				"internalType": "string",
				"name": "_productData",
				"type": "string"
			}
		],
		"name": "verifyProduct",
		"outputs": [
			{
				"internalType": "bool",
				"name": "",
				"type": "bool"
			}
		],
		"stateMutability": "view",
		"type": "function"
	},
	{
		"inputs": [
			{
				"internalType": "address",
				"name": "",
				"type": "address"
			}
		],
		"name": "balances",
		"outputs": [
			{
				"internalType": "uint256",
				"name": "",
				"type": "uint256"
			}
		],
		"stateMutability": "view",
		"type": "function"
	},
	{
		"inputs": [],
		"name": "deposit",
		"outputs": [],
		"stateMutability": "payable",
		"type": "function"
	},
	{
		"inputs": [
			{
				"internalType": "address",
				"name": "_to",
				"type": "address"
			},
			{
				"internalType": "uint256",
				"name": "_amount",
				"type": "uint256"
			}
		],
		"name": "transferBalance",
		"outputs": [],
		"stateMutability": "nonpayable",
		"type": "function"
	}
]; //YOUR ABI CONTENTS GO HERE

function toWei(ethString){
  return ethers.parseUnits(String(ethString), 18);
}

async function getSignerAndContract(){
  if (!window.ethereum) throw new Error("MetaMask not found");
  await ethereum.request({ method: 'eth_requestAccounts' });
  const provider = new ethers.BrowserProvider(window.ethereum);
  const signer = await provider.getSigner();
  const contract = new ethers.Contract(CONTRACT_ADDRESS, ABI, signer);
  return { signer, contract };
}

// ------- Balance helpers (internal balance feature) -------
// CRITICAL: All balance functions now take the user's ETH address as a parameter
// This ensures each user's balance is tracked independently in the contract

// Query the contract.balances(userAddress) mapping to get user balance
async function getBalanceOfAddress(address) {
	if (!address || !address.startsWith('0x')) {
		throw new Error('Invalid address: ' + address);
	}
	const { contract } = await getSignerAndContract();
	try {
		// Call the public balances(address) mapping from contract
		// This reads the balance for the SPECIFIC address passed in
		const balance = await contract.balances(address);
		// ethers.js v6 returns BigInt; convert to string
		return String(balance || 0n);
	} catch (err) {
		console.error('Error reading balance for', address, ':', err);
		throw new Error('Failed to read balance: ' + (err?.message || err));
	}
}

// Deposit credits - sends ETH to contract which tracks internal balance
// This increases the CALLER's balance (msg.sender in Solidity)
async function deposit(amount) {
	if (!amount || isNaN(amount)) {
		throw new Error('Invalid deposit amount: ' + amount);
	}
	const { contract, signer } = await getSignerAndContract();
	const userAddr = await signer.getAddress();
	try {
		console.log('Depositing', amount, 'wei for user:', userAddr);
		// Send ETH value with transaction - contract will track it
		const tx = await contract.deposit({ value: BigInt(amount) });
		const receipt = await tx.wait();
		console.log('Deposit tx:', receipt?.hash || tx.hash);
		return receipt?.hash || tx.hash;
	} catch (err) {
		console.error('Error depositing:', err);
		throw new Error('Deposit failed: ' + (err?.message || err));
	}
}

// Transfer balance to another address
// fromAddress: the sender's ETH address (must match MetaMask wallet)
// toAddress: the recipient's ETH address  
// amount: amount in wei (internal balance units)
async function transferBalance(fromAddress, toAddress, amount) {
	if (!fromAddress || !fromAddress.startsWith('0x')) {
		throw new Error('Invalid from address: ' + fromAddress);
	}
	if (!toAddress || !toAddress.startsWith('0x')) {
		throw new Error('Invalid to address: ' + toAddress);
	}
	if (!amount || isNaN(amount)) {
		throw new Error('Invalid transfer amount: ' + amount);
	}
	const { contract, signer } = await getSignerAndContract();
	const connectedAddr = await signer.getAddress();
	
	// Verify the connected wallet matches the sender
	if (connectedAddr.toLowerCase() !== fromAddress.toLowerCase()) {
		throw new Error('Wallet mismatch: Connected wallet is ' + connectedAddr + ' but sender is ' + fromAddress);
	}
	
	try {
		console.log('Transferring from:', fromAddress, 'to:', toAddress, 'amount:', amount);
		// Call the transferBalance(address, uint256) function
		// The contract will deduct from balances[msg.sender] and add to balances[toAddress]
		const tx = await contract.transferBalance(toAddress, BigInt(amount));
		const receipt = await tx.wait();
		console.log('Transfer tx:', receipt?.hash || tx.hash);
		return receipt?.hash || tx.hash;
	} catch (err) {
		console.error('Error transferring balance:', err);
		throw new Error('Transfer failed: ' + (err?.message || err));
	}
}

// Wire UI buttons for balance actions with proper event listeners
// IMPORTANT: USER_ETH_ADDRESS must be passed in as a global variable from the PHP page
// Example: <script>const USER_ETH_ADDRESS = '0x...';</script>
function wireBalanceButtons(){
	console.log('wireBalanceButtons() called'); // Debug log
	
	// Verify user ETH address is available
	if (typeof USER_ETH_ADDRESS === 'undefined' || !USER_ETH_ADDRESS) {
		console.error('USER_ETH_ADDRESS is not defined. Check if PHP passed it correctly.');
		return;
	}
	console.log('User ETH address from PHP:', USER_ETH_ADDRESS);
	
	// ===== SHOW BALANCE BUTTON =====
	// Query THIS user's balance using contract.balances(USER_ETH_ADDRESS)
	document.querySelectorAll('#btn-show-balance').forEach(btn => {
		console.log('Wiring btn-show-balance:', btn);
		btn.addEventListener('click', async () => {
			btn.disabled = true;
			btn.textContent = 'Reading…';
			try {
				console.log('Getting balance for:', USER_ETH_ADDRESS);
				const balance = await getBalanceOfAddress(USER_ETH_ADDRESS);
				console.log('Balance retrieved:', balance);
				alert(`Your Balance: ${balance} wei\n\nAddress: ${USER_ETH_ADDRESS}`);
			} catch (err) {
				console.error('Show balance error:', err);
				alert('Error reading balance:\n' + (err?.message || err));
			}
			btn.disabled = false;
			btn.textContent = '📊 Show Balance';
		});
	});

	// ===== ADD CREDITS (DEPOSIT) BUTTON =====
	// Deposit ETH to increase internal balance
	document.querySelectorAll('#btn-add-credits').forEach(btn => {
		console.log('Wiring btn-add-credits:', btn);
		btn.addEventListener('click', async () => {
			const amountWei = prompt('Enter amount to deposit (in wei):\n\nExample: 1000000000000000000 = 1 ETH\n\nDefault: 100000000000000000 (0.1 ETH)');
			if (!amountWei || isNaN(amountWei)) {
				alert('Invalid amount');
				return;
			}
			if (!confirm(`Deposit ${amountWei} wei to your balance?\n\nThis will call contract.deposit() with ETH value.`)) {
				return;
			}
			btn.disabled = true;
			btn.textContent = 'Depositing…';
			try {
				console.log('Depositing:', amountWei);
				const txh = await deposit(amountWei);
				console.log('Deposit tx hash:', txh);
				alert(`✅ Deposit successful!\n\nTransaction: ${txh}\n\nYour balance increased by ${amountWei} wei.`);
			} catch (err) {
				console.error('Deposit error:', err);
				alert('❌ Deposit failed:\n' + (err?.message || err));
			}
			btn.disabled = false;
			btn.textContent = '➕ Add Credits';
		});
	});

	// ===== PAY (TRANSFER BALANCE) BUTTON =====
	// Transfer balance from THIS user to another user selected from dropdown
	document.querySelectorAll('#btn-balance-transfer').forEach(btn => {
		console.log('Wiring btn-balance-transfer:', btn);
		btn.addEventListener('click', async () => {
			// Get selected recipient from dropdown
			const recipientSelect = btn.closest('div').querySelector('#transfer-recipient');
			const toAddress = recipientSelect?.value;
			const recipientUsername = recipientSelect?.querySelector('option:checked')?.dataset?.username;
			
			if (!toAddress) {
				alert('Please select a recipient from the dropdown');
				return;
			}
			
			const amountWei = prompt('Enter amount to transfer (in wei):\n\nExample: 1000000000000000000 = 1 ETH');
			if (!amountWei || isNaN(amountWei)) {
				alert('Invalid amount');
				return;
			}
			
			const confirmMsg = `Send ${amountWei} wei to ${recipientUsername}?\n\nFrom: ${USER_ETH_ADDRESS}\nTo:   ${toAddress}\n\n⬇️ Your balance will DECREASE\n⬆️ Their balance will INCREASE`;
			if (!confirm(confirmMsg)) {
				return;
			}
			
			btn.disabled = true;
			btn.textContent = 'Transferring…';
			try {
				console.log('Transferring from:', USER_ETH_ADDRESS, 'to:', toAddress, 'amount:', amountWei);
				const txh = await transferBalance(USER_ETH_ADDRESS, toAddress, amountWei);
				console.log('Transfer tx hash:', txh);
				alert(`✅ Transfer successful!\n\nTransaction: ${txh}\n\nYour balance: -${amountWei} wei\n${recipientUsername}'s balance: +${amountWei} wei`);
			} catch (err) {
				console.error('Transfer error:', err);
				alert('❌ Transfer failed:\n' + (err?.message || err));
			}
			btn.disabled = false;
			btn.textContent = '💸 Pay';
		});
	});
}


// Approve handler: call chain → on success, submit form with tx hash
async function handleApproveOnChain(row) {
  const id   = row.dataset.id;
  const name = row.dataset.name;
  const price= row.dataset.price; // displayed currency; adapt if you keep ETH elsewhere
  const qty  = row.dataset.qty;

  // Simple meta payload: include local product id + name (or use IPFS and put CID here)
  const metaHash = `local:${id}|${name}`;

  const { contract } = await getSignerAndContract();
	// Call the on-chain registration function from the ABI.
	// ABI exposes `registerProduct(uint256 _productId, string _productData)`
	// so we pass the local id and a small meta payload.
	const tx = await contract.registerProduct(BigInt(id), metaHash);
  const receipt = await tx.wait();

  return receipt?.hash || tx.hash; // v6 returns tx.hash; receipt.hash is the same
}

function wireApproveButtons(){
  const buttons = document.querySelectorAll('.btn-onchain-approve');
  buttons.forEach(btn => {
    btn.addEventListener('click', async () => {
      const row = btn.closest('tr');
      const form = row.querySelector('form.approve-form');
      const txInput = form.querySelector('input[name="txhash"]');

      btn.disabled = true;
      btn.textContent = 'Approving…';
      

      try {
        const txhash = await handleApproveOnChain(row);
        txInput.value = txhash || '';
        // After chain success, submit the PHP form to mark approved + show tx
        form.submit();
      } catch (err) {
        console.error(err);
        alert('On-chain approval failed: ' + (err?.shortMessage || err?.message || err));
        btn.disabled = false;
        btn.textContent = 'Approve';
      }
    });
  });
}

// Supply (supplier purchase) handler: transfer ownership on-chain
async function handleSupplyOnChain(row) {
	const id = row.dataset.id;
	const { signer, contract } = await getSignerAndContract();
	const addr = await signer.getAddress();
	const tx = await contract.transferProduct(BigInt(id), addr);
	const receipt = await tx.wait();
	return receipt?.hash || tx.hash;
}

function wireSupplyButtons(){
	const buttons = document.querySelectorAll('.btn-onchain-supply');
	buttons.forEach(btn => {
		btn.addEventListener('click', async () => {
			const row = btn.closest('tr');
			const form = row.querySelector('form.supply-form');
			const txInput = form.querySelector('input[name="txhash"]');
			btn.disabled = true;
			btn.textContent = 'Purchasing…';
			try {
				const txhash = await handleSupplyOnChain(row);
				txInput.value = txhash || '';
				form.submit();
			} catch (err) {
				console.error(err);
				alert('On-chain purchase failed: ' + (err?.shortMessage || err?.message || err));
				btn.disabled = false;
				btn.textContent = 'Purchase';
			}
		});
	});
}

window.addEventListener('load', wireApproveButtons);
window.addEventListener('load', wireSupplyButtons);
// Wire balance buttons when page is loaded so UI buttons react
window.addEventListener('load', wireBalanceButtons);