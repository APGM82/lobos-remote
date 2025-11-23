import {getUsers,getFilterUsers, addUser, updateUser, deleteUser} from './CrudUser.ts'

let selectedUser = 0
let currentPage = 1 // Página actual de la paginación
let currentFilter = '' // Filtro de búsqueda actual para mantenerlo al cambiar de página
let paginationInfo: any = null // Información de paginación recibida del backend

const filter = (document.getElementById('filter') as HTMLInputElement)!;

/**
 * Mostrar usuarios con paginación
 * Carga los usuarios de la página actual y renderiza la tabla junto con los controles de paginación
 */
const showUsers = () => {
    // Si hay un filtro activo, usar filterUsers, sino usar loadUsers
    if (currentFilter.trim()) {
        filterUsers(currentFilter).then(result => {
            handleUsersResponse(result)
        })
    } else {
        loadUsers().then(result => {
            handleUsersResponse(result)
        })
    }
}

/**
 * Maneja la respuesta de los usuarios (con o sin paginación)
 * @param result - Objeto con datos y paginación, o array de usuarios
 */
const handleUsersResponse = (result: any) => {
    let users: any[] = []
    let pagination: any = null

    // Verificar si la respuesta incluye paginación
    if (result && typeof result === 'object' && 'data' in result && 'pagination' in result) {
        users = result.data || []
        pagination = result.pagination
        paginationInfo = pagination
    } else if (Array.isArray(result)) {
        // Compatibilidad con formato antiguo (sin paginación)
        users = result
    }

    if (users.length === 0) {
        // Ocultar la tabla y mostrar el mensaje de error
        const table = document.querySelector('#tableContainer table')
        table?.classList.add('disabled')

        const usersError = document.getElementById('usersError')
        usersError?.classList.remove('disabled')
        
        // Limpiar paginación si no hay resultados
        renderPagination(null)
    } else {
        // Mostrar la tabla y ocultar el error
        const table = document.querySelector('#tableContainer table')
        table?.classList.remove('disabled')

        const usersError = document.getElementById('usersError')
        usersError?.classList.add('disabled')

        chargeTable(users)
        renderPagination(pagination)
    }
}

/**
 * Event listener para el filtro de búsqueda
 * Al escribir, resetea a la página 1 y aplica el filtro
 */
filter.addEventListener('keyup', async () => {
    currentFilter = filter.value
    currentPage = 1 // Resetear a la primera página al filtrar
    showUsers() // Recargar con el nuevo filtro
})
const chargeTable = (users: any[]) => {
    document.getElementById('usersTable')!.innerHTML = ""
    users.forEach((user) => {
        const row = document.createElement('tr');

        const tdImage = document.createElement('td');
        tdImage.classList.add('text-center', 'w-10');
        const userImage = document.createElement('img');
        userImage.src = user.image;
        userImage.alt = user.name;
        userImage.classList.add('rounded-circle');
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
        tdActions.classList.add('d-flex', 'align-items-center', 'justify-content-center', 'text-center', 'h-100');

        const div = document.createElement('div');
        div.classList.add('d-flex', 'align-items-center', 'justify-content-center', 'text-center', 'h-100', 'bg-white', 'border-none');
        tdActions.appendChild(div);

        const editButton = document.createElement('button');
        editButton.classList.add('btn', 'warning');
        editButton.id = 'deleteUserBtn';
        editButton.value = user.id;
        editButton.textContent = 'Editar';
        div.appendChild(editButton);

        editButton.addEventListener('click', () => {
            selectedUser = Number(editButton!.value)
            const modifyModal = document.getElementById('modifyModal');
            modifyModal!.style.display = 'flex';
        })

        const deleteButton = document.createElement('button');
        deleteButton.classList.add('btn', 'danger');
        deleteButton.id = 'deleteUserBtn';
        deleteButton.value = user.id;
        deleteButton.textContent = 'Eliminar';
        div.appendChild(deleteButton);

        deleteButton.addEventListener('click', () => {
            selectedUser = Number(deleteButton!.value)
            const deleteModal = document.getElementById('deleteModal');
            deleteModal!.style.display = 'flex';
        });

        row.appendChild(tdActions);

        document.getElementById('usersTable')?.appendChild(row);
    })
}

/**
 * Cargar usuarios con paginación
 * @returns Promise con objeto que contiene data (array de usuarios) y pagination (metadatos)
 */
const loadUsers = async (): Promise<any> => {
    try {
        const response = await getUsers(currentPage);
        
        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = 'login.html'
                return { data: [], pagination: null }
            }
            return { data: [], pagination: null }
        }
        
        const responseData = await response.json();
        
        if (responseData.success && responseData.data) {
            // Devolver tanto los datos como la información de paginación
            return {
                data: responseData.data,
                pagination: responseData.pagination || null
            }
        }
        return { data: [], pagination: null }
    } catch(e) {
        console.error("Error al cargar los usuarios:", e);
        return { data: [], pagination: null }
    }
};

/**
 * Filtrar usuarios por nickname con paginación
 * @param nickname - Término de búsqueda
 * @returns Promise con objeto que contiene data (array de usuarios) y pagination (metadatos)
 */
const filterUsers = async (nickname : String): Promise<any> => {
    try {
        if (!nickname || nickname.trim() === '') {
            // Si no hay filtro, cargar todos los usuarios
            return await loadUsers()
        }
        
        const response = await getFilterUsers(nickname.trim(), currentPage);
        
        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = 'login.html'
                return { data: [], pagination: null }
            }
            return { data: [], pagination: null }
        }
        
        const responseData = await response.json();
        
        if (responseData.success && responseData.data) {
            // Devolver tanto los datos como la información de paginación
            return {
                data: responseData.data,
                pagination: responseData.pagination || null
            }
        }
        return { data: [], pagination: null }
    } catch(e) {
        console.error("Error al filtrar los usuarios:", e);
        return { data: [], pagination: null }
    }
}

/**
 * Cambiar a una página específica
 * @param page - Número de página a la que cambiar
 */
const changePage = (page: number) => {
    if (page < 1 || (paginationInfo && page > paginationInfo.last_page)) {
        return // No hacer nada si la página es inválida
    }
    currentPage = page
    showUsers() // Recargar usuarios de la nueva página
}

/**
 * Renderizar los controles de paginación
 * @param pagination - Objeto con información de paginación (current_page, last_page, total, etc.)
 */
const renderPagination = (pagination: any) => {
    const container = document.getElementById('paginationContainer')
    if (!container) return

    // Limpiar contenedor
    container.innerHTML = ''

    // Si no hay información de paginación o solo hay una página, no mostrar controles
    if (!pagination || pagination.last_page <= 1) {
        return
    }

    const { current_page, last_page } = pagination

    // Crear contenedor principal
    const paginationDiv = document.createElement('div')
    paginationDiv.classList.add('pagination-container')

    // Contenedor para números de página
    const pageNumbersDiv = document.createElement('div')
    pageNumbersDiv.classList.add('pagination-numbers')

    // Calcular qué números de página mostrar (máximo 5 alrededor de la página actual)
    let startPage = Math.max(1, current_page - 2)
    let endPage = Math.min(last_page, current_page + 2)

    // Ajustar si estamos cerca del inicio o del final
    if (endPage - startPage < 4) {
        if (startPage === 1) {
            endPage = Math.min(last_page, startPage + 4)
        } else if (endPage === last_page) {
            startPage = Math.max(1, endPage - 4)
        }
    }

    // Mostrar primera página si no está en el rango
    if (startPage > 1) {
        const firstBtn = document.createElement('button')
        firstBtn.classList.add('pagination-number')
        firstBtn.textContent = '1'
        firstBtn.addEventListener('click', () => changePage(1))
        pageNumbersDiv.appendChild(firstBtn)

        if (startPage > 2) {
            const ellipsis = document.createElement('button')
            ellipsis.classList.add('pagination-ellipsis')
            ellipsis.textContent = '...'
            ellipsis.disabled = true
            pageNumbersDiv.appendChild(ellipsis)
        }
    }

    // Mostrar números de página en el rango calculado
    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button')
        pageBtn.classList.add('pagination-number')
        if (i === current_page) {
            pageBtn.classList.add('active')
        }
        pageBtn.textContent = i.toString()
        pageBtn.addEventListener('click', () => changePage(i))
        pageNumbersDiv.appendChild(pageBtn)
    }

    // Mostrar última página si no está en el rango
    if (endPage < last_page) {
        if (endPage < last_page - 1) {
            const ellipsis = document.createElement('button')
            ellipsis.classList.add('pagination-ellipsis')
            ellipsis.textContent = '...'
            ellipsis.disabled = true
            pageNumbersDiv.appendChild(ellipsis)
        }

        const lastBtn = document.createElement('button')
        lastBtn.classList.add('pagination-number')
        lastBtn.textContent = last_page.toString()
        lastBtn.addEventListener('click', () => changePage(last_page))
        pageNumbersDiv.appendChild(lastBtn)
    }

    paginationDiv.appendChild(pageNumbersDiv)

    container.appendChild(paginationDiv)
}

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
                         <label for="aName">Nombre: </label>
                        <input type="text" name="aName" id="aName" required>
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
    const modifyModal = document.createElement('div');
    modifyModal.innerHTML = `
    <div class="modal-overlay">
        <div class="modifyModal">
            <div class="modal-header">
                 <h2>Modificar usuario</h2>
            </div>
            <form action="" method="post" id="modifyModalForm">
                <div class="modal-body">
    
                    <div>
                        <label for="mName">Nombre: </label>
                        <input type="text" name="mName" id="mName" value="">
                    </div>
                    
                    <div>
                        <label for="image">Imagen de perfil: </label>
                        <input type="file" name="image" id="image" accept="image/jpeg, image/png, image/jpg" value=""/>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="modifyBtn" class="btn success">Modificar</button>
                    <button id="closeModifyBtn" class="btn warning">Cerrar</button>
                </div>
            </form>
        </div>
    </div>
    `

    const deleteModal = document.createElement('div');
    deleteModal.innerHTML += `
    <div class="modal-overlay">
        <div class="deleteModal">
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

    addModal.style.display = 'none'
    modifyModal.style.display = 'none'
    deleteModal.style.display = 'none'
    addModal.setAttribute('id', 'addModal');
    modifyModal.setAttribute('id', 'modifyModal');
    deleteModal.setAttribute('id', 'deleteModal');
    body!.appendChild(addModal);
    body!.appendChild(modifyModal);
    body!.appendChild(deleteModal);

    const addUserBtn = document.getElementById('addUserBtn');

    addUserBtn!.addEventListener('click', () => {
        addModal.style.display = 'flex';
    });

    const closeBtn = document.getElementById('closeAddBtn');
    closeBtn!.addEventListener('click', () => {
        const form = document.getElementById('addModalForm') as HTMLFormElement;
        addModal.style.display = 'none';
        form!.reset();
    });

    const addBtn = document.getElementById('addBtn');
    addBtn!.addEventListener('click', async (event) => {
        event.preventDefault();
        const name = (document.getElementById('aName') as HTMLInputElement)!.value
        const nickname = (document.getElementById('nickname') as HTMLInputElement)!.value
        const mail = (document.getElementById('mail') as HTMLInputElement)!.value

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

        const form = document.getElementById('addModalForm') as HTMLFormElement;
        addModal.style.display = 'none';
        form!.reset();
        currentPage = 1 // Resetear a la primera página
        await showUsers();
    })


    const closeModifyBtn = document.getElementById('closeModifyBtn');
    closeModifyBtn!.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.getElementById('modifyModalForm') as HTMLFormElement;
        modifyModal.style.display = 'none';
        form!.reset()
    })

    const modifyBtn = document.getElementById('modifyBtn');
    modifyBtn!.addEventListener('click', async (e) => {
        e.preventDefault()
        const formData = new FormData();
        const name = (document.getElementById('mName') as HTMLInputElement)!.value
        formData.append('name', name);
        const imageInput = document.getElementById('image') as HTMLInputElement;
        if (imageInput!.files && imageInput!.files.length > 0) {
            formData.append('image', imageInput!.files[0]);
        }

        try {
            const response = await updateUser(selectedUser, formData);

            const responseData = await response.json();
            console.log("Agregado con éxito:", responseData);

            currentPage = 1 // Resetear a la primera página
            await showUsers();
        } catch (e) {
            console.error("Error al modificar el usuario:", e);
        }

        const form = document.getElementById('modifyModalForm') as HTMLFormElement;
        modifyModal.style.display = 'none';
        form!.reset();
        currentPage = 1 // Resetear a la primera página
        await showUsers();
    })

    const closeDeleteBtn = document.getElementById('closeDeleteBtn');
    closeDeleteBtn!.addEventListener('click', (e) => {
        e.preventDefault();
        deleteModal!.style.display = 'none';

    });



    const deleteBtn = document.getElementById('deleteBtn');
    deleteBtn!.addEventListener('click', async (e) => {
        e.preventDefault();
        try {
            await deleteUser(selectedUser);
            console.log("Eliminado con éxito");

            deleteModal.style.display = 'none'

            currentPage = 1 // Resetear a la primera página
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
        if (event.target === modifyModal) {
            modifyModal.style.display = 'none';
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