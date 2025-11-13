import {getUsers, addUser, deleteUser} from './CrudUser.ts'

let selectedUser = ""

const showUsers = () =>
    loadUsers().then(users => {
        if (users.length === 0) {
            const usersContainer = document.getElementById('usersContainer');
            usersContainer?.classList.add('disabled');

            const usersError = document.getElementById('usersError');
            usersError?.classList.remove('disabled');
        } else {
            const usersContainer = document.getElementById('usersContainer');
            usersContainer?.classList.remove('disabled');

            const usersError = document.getElementById('usersError');
            usersError?.classList.add('disabled');

            chargeTable(users)
        }
    });


const chargeTable = (users: any[]) => {
    document.getElementById('usersTable')!.innerHTML = ""
    users.forEach((user) => {
        const row = document.createElement('tr');

        const tdImage = document.createElement('td');
        tdImage.classList.add('text-center');
        const userImage = document.createElement('img');
        userImage.src = user.image;
        userImage.alt = user.name;
        tdImage.appendChild(userImage);
        row.appendChild(tdImage);

        const tdName = document.createElement('td');
        tdName.classList.add('text-center');
        tdName.textContent = user.name;
        row.appendChild(tdName);

        const tdNickname = document.createElement('td');
        tdNickname.classList.add('text-center');
        tdNickname.textContent = user.nickname;
        row.appendChild(tdNickname);

        const tdEmail = document.createElement('td');
        tdEmail.classList.add('text-center');
        tdEmail.textContent = user.email;
        row.appendChild(tdEmail);

        const tdActions = document.createElement('td');
        tdActions.classList.add('d-flex', 'align-items-center', 'justify-content-center', 'text-center');

        const editButton = document.createElement('button');
        editButton.classList.add('btn', 'warning');
        editButton.textContent = 'Editar';
        tdActions.appendChild(editButton);

        const deleteButton = document.createElement('button');
        deleteButton.classList.add('btn', 'danger');
        deleteButton.id = 'deleteUserBtn';
        deleteButton.value = user.id;
        deleteButton.textContent = 'Eliminar';
        tdActions.appendChild(deleteButton);

        deleteButton.addEventListener('click', () => {
            selectedUser = deleteButton!.value
            const deleteModal = document.getElementById('deleteModal');
            deleteModal!.style.display = 'flex';
        });

        row.appendChild(tdActions);

        document.getElementById('usersTable')?.appendChild(row);
    })
}

const loadUsers = async () => {
    try {
        const response = await getUsers();
        const responseData = await response.json();
        return responseData.data;
    } catch(e) {
        console.error("Error al cargar los usuarios:", e);
        return [];
    }
};

const generateModals = () => {
    const body = document.querySelector('body');

    const addModal = document.createElement('div');
    addModal.innerHTML = `
    <div class="modal-overlay">
        <div class="addModal">
            <div class="modal-header">
                 <h2>Añadir usuario</h2>
            </div>
            <form action="" method="post" id="addModalForm">
                <div class="modal-body">
    
                    <div>
                         <label for="name">Nombre: </label>
                        <input type="text" name="name" id="name" required>
                    </div>
                    <div>
                        <label for="nickname">Nickname: </label>
                        <input type="text" name="nickname" id="nickname" required>  
                    </div>
                    <div>
                        <label for="mail">Correo electronico: </label>
                        <input type="text" name="mail" id="mail" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="addBtn" class="btn success">Agregar</button>
                    <button id="closeAddBtn" class="btn warning">Cerrar</button>
                </div>
            </form>
        </div>
    </div>
    `
    const deleteModal = document.createElement('div');
    deleteModal.innerHTML += `
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                 <h2>Eliminar usuario</h2>
            </div>
            <form action="" method="post" id="deleteModalForm">
                <div class="modal-body">
    
                    <div>
                         <p>Estas seguro que quieres eliminar el usuario</p>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button id="deleteBtn" class="btn danger">Eliminar</button>
                    <button id="closeDeleteBtn" class="btn warning">Cerrar</button>
                </div>
            </form>
        </div>
    </div>
    `
    deleteModal.style.display = 'none'
    addModal.style.display = 'none'
    addModal.setAttribute('id', 'addModal');
    deleteModal.setAttribute('id', 'deleteModal');
    body!.appendChild(addModal);
    body!.appendChild(deleteModal);

    const addUserBtn = document.getElementById('addUserBtn');

    addUserBtn!.addEventListener('click', () => {
        addModal.style.display = 'flex';
    });

    const closeBtn = document.getElementById('closeAddBtn');
    closeBtn!.addEventListener('click', () => {
        const form = document.getElementById('addModalForm');
        addModal.style.display = 'none';
        form!.reset();
    });

    const addBtn = document.getElementById('addBtn');
    addBtn!.addEventListener('click', async (event) => {
        event.preventDefault();
        const name = document.getElementById('name')!.value
        const nickname = document.getElementById('nickname')!.value
        const mail = document.getElementById('mail')!.value

        const data = {
            "name" : name,
            "nickname" : nickname,
            "email" : mail
        }

        try {
            const response = await addUser(data);

            const responseData = await response.json();
            console.log("Agregado con éxito:", responseData);

        } catch (e) {
            console.error("Error al agregar un usuario:", e);
        }

        const form = document.getElementById('addModalForm');
        addModal.style.display = 'none';
        form!.reset();
        await showUsers();
    })

    const closeDeleteBtn = document.getElementById('closeDeleteBtn');
    closeDeleteBtn!.addEventListener('click', () => {

        closeDeleteBtn!.style.display = 'none';

    });

    const deleteBtn = document.getElementById('deleteBtn');
    deleteBtn!.addEventListener('click', async (e) => {
        e.preventDefault();
        try {
            await deleteUser(selectedUser);
            console.log("Eliminado con éxito");

            deleteModal.style.display = 'none'

            await showUsers();

        } catch (e) {
            console.error("Error al eliminar un usuario:", e);
        }
    })

    window.addEventListener('click', (event) => {
        if (event.target === addModal) {
            addModal.style.display = 'none';
        }
    });

    window.addEventListener('click', (event) => {
        if (event.target === deleteModal) {
            deleteModal.style.display = 'none'
        }
    });
}

const init = () => {
    generateModals()
    showUsers();
};

export default init;