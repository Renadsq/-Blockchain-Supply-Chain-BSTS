// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract SupplyChain {

    struct Product {
        uint256 productId;
        address owner;
        uint256 timestamp;
        bytes32 productInfoHash;
        bool exists;
    }

    mapping(uint256 => Product) public products;

    // Ownership history
    mapping(uint256 => address[]) private ownershipHistory;

    event ProductRegistered(
        uint256 indexed productId,
        address indexed producer,
        uint256 timestamp,
        bytes32 dataHash
    );

    event ProductTransferred(
        uint256 indexed productId,
        address indexed from,
        address indexed to,
        uint256 timestamp
    );

    // Register product with hashing
    function registerProduct(uint256 _productId, string memory _productData) public {
        require(products[_productId].exists == false, "Product already registered");

        bytes32 dataHash = keccak256(abi.encodePacked(_productData));

        products[_productId] = Product({
            productId: _productId,
            owner: msg.sender,
            timestamp: block.timestamp,
            productInfoHash: dataHash,
            exists: true
        });

        ownershipHistory[_productId].push(msg.sender);

        emit ProductRegistered(_productId, msg.sender, block.timestamp, dataHash);
    }

    // Transfer ownership
    function transferProduct(uint256 _productId, address _newOwner) public {
        require(products[_productId].exists, "Product does not exist");
        require(msg.sender == products[_productId].owner, "Only owner can transfer");
        require(_newOwner != address(0), "Invalid address");

        address previousOwner = products[_productId].owner;

        products[_productId].owner = _newOwner;

        ownershipHistory[_productId].push(_newOwner);

        emit ProductTransferred(_productId, previousOwner, _newOwner, block.timestamp);
    }

    // Get product history
    function getProductHistory(uint256 _productId) public view returns (address[] memory) {
        require(products[_productId].exists, "Product does not exist");
        return ownershipHistory[_productId];
    }

    // ----------------------------------------------------
    // PRODUCT VERIFICATION FUNCTION
    // ----------------------------------------------------
    // Consumer enters productId + original product data.
    // Contract hashes data and compares with stored hash.
    function verifyProduct(uint256 _productId, string memory _productData)
        public
        view
        returns (bool)
    {
        require(products[_productId].exists, "Product not found");

        bytes32 dataHash = keccak256(abi.encodePacked(_productData));

        return (dataHash == products[_productId].productInfoHash);
    }
}

